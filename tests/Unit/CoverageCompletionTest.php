<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\WioPayments;
use Wio\WioPayments\Exceptions\PaymentFailedException;
use Wio\WioPayments\Support\HttpClient;
use Mockery;

class CoverageCompletionTest extends TestCase
{
    private WioPayments $wioPayments;
    private $httpClientMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClientMock = Mockery::mock(HttpClient::class);
        $this->wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        // Inject mock using reflection
        $reflection = new \ReflectionClass($this->wioPayments);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->wioPayments, $this->httpClientMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_throws_exception_when_creating_test_payment_without_test_mode(): void
    {
        // Test mode is false by default
        $this->assertFalse($this->wioPayments->isTestMode());
        
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Test mode must be enabled to create test payments');
        
        $this->wioPayments->createTestPayment('USD', 1000, 'success');
    }

    /** @test */
    public function it_throws_exception_when_simulating_webhook_without_test_mode(): void
    {
        // Test mode is false by default
        $this->assertFalse($this->wioPayments->isTestMode());
        
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Test mode must be enabled to simulate webhooks');
        
        $this->wioPayments->simulateWebhook('payment.succeeded', ['payment_id' => 'pay_123']);
    }

    /** @test */
    public function it_handles_different_test_payment_scenarios(): void
    {
        $this->wioPayments->setTestMode(true);
        
        $mockResponse = [
            'id' => 'test_pay_timeout',
            'status' => 'timeout',
            'test_mode' => true,
            'test_scenario' => 'timeout'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/test/payments', [
                'currency' => 'EUR',
                'amount' => 2500,
                'test_scenario' => 'timeout',
                'test_mode' => true
            ])
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createTestPayment('EUR', 2500, 'timeout');
        
        $this->assertInstanceOf(\Wio\WioPayments\DTOs\PaymentResponse::class, $response);
    }

    /** @test */
    public function it_simulates_different_webhook_events(): void
    {
        $this->wioPayments->setTestMode(true);
        
        $mockResponse = [
            'event_id' => 'evt_test_123',
            'status' => 'delivered',
            'test_mode' => true
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/test/webhooks', [
                'event_type' => 'payment.failed',
                'test_mode' => true,
                'data' => ['payment_id' => 'pay_456', 'error_code' => 'card_declined']
            ])
            ->andReturn($mockResponse);

        $response = $this->wioPayments->simulateWebhook('payment.failed', [
            'payment_id' => 'pay_456',
            'error_code' => 'card_declined'
        ]);
        
        $this->assertArrayHasKey('event_id', $response);
        $this->assertEquals('evt_test_123', $response['event_id']);
        $this->assertTrue($response['test_mode']);
    }

    /** @test */
    public function it_handles_test_mode_chaining(): void
    {
        // Test fluent interface
        $result = $this->wioPayments->setTestMode(true);
        $this->assertSame($this->wioPayments, $result);
        $this->assertTrue($this->wioPayments->isTestMode());
        
        // Chain multiple calls
        $result2 = $this->wioPayments->setTestMode(false)->setTestMode(true);
        $this->assertSame($this->wioPayments, $result2);
        $this->assertTrue($this->wioPayments->isTestMode());
    }

    /** @test */
    public function it_validates_api_credentials_with_exception_handling(): void
    {
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/account/verify')
            ->andThrow(new \Exception('Network timeout'));

        $result = $this->wioPayments->validateApiCredentials();
        
        $this->assertFalse($result['valid']);
        $this->assertEquals('Network timeout', $result['error']);
    }

    /** @test */
    public function it_handles_webhook_timestamp_edge_cases(): void
    {
        $payload = '{"event": "payment.succeeded"}';
        $currentTime = time();
        
        // Test with null timestamp (should use current time)
        $signature = hash_hmac('sha256', $payload . $currentTime, 'test_secret_1234567890');
        $result = $this->wioPayments->verifyWebhookSignature($payload, $signature, null);
        $this->assertTrue($result);
        
        // Test with timestamp exactly at 300 second boundary (should pass)
        $boundaryTime = $currentTime - 300;
        $boundarySignature = hash_hmac('sha256', $payload . $boundaryTime, 'test_secret_1234567890');
        $result = $this->wioPayments->verifyWebhookSignature($payload, $boundarySignature, (string)$boundaryTime);
        $this->assertTrue($result);
        
        // Test with timestamp just over 300 seconds (should fail)
        $expiredTime = $currentTime - 301;
        $expiredSignature = hash_hmac('sha256', $payload . $expiredTime, 'test_secret_1234567890');
        $result = $this->wioPayments->verifyWebhookSignature($payload, $expiredSignature, (string)$expiredTime);
        $this->assertFalse($result);
    }
}