<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\WioPayments;
use Wio\WioPayments\Exceptions\PaymentFailedException;
use Wio\WioPayments\Exceptions\InvalidCurrencyException;
use Wio\WioPayments\Support\HttpClient;
use Mockery;

class FinalCoverageTest extends TestCase
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
    public function it_formats_amount_for_jpy_currency_without_decimals(): void
    {
        // Test JPY specific formatting path
        $result = WioPayments::formatAmount(1500, 'JPY');
        $this->assertEquals('¥1,500', $result);
        
        // Test lowercase jpy
        $result = WioPayments::formatAmount(12345, 'jpy');
        $this->assertEquals('¥12,345', $result);
    }

    /** @test */
    public function it_formats_amount_for_unknown_currency(): void
    {
        // Test path where currency symbol is not in predefined list
        $result = WioPayments::formatAmount(1000, 'XYZ');
        $this->assertEquals('XYZ10.00', $result);
        
        // Test with lowercase unknown currency
        $result = WioPayments::formatAmount(2500, 'abc');
        $this->assertEquals('ABC25.00', $result);
    }

    /** @test */
    public function it_validates_maximum_amount_limit(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount exceeds maximum allowed limit');
        
        // Test exactly at the boundary (should fail)
        $this->httpClientMock
            ->shouldReceive('post')
            ->never();
            
        $this->wioPayments->charge('USD', 100000000); // 1,000,000.00 in cents
    }

    /** @test */
    public function it_validates_maximum_amount_boundary(): void
    {
        // Test just under the limit (should pass validation)
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/create-payment', Mockery::type('array'))
            ->andReturn(['id' => 'pay_123', 'status' => 'created']);
            
        $response = $this->wioPayments->charge('USD', 99999999); // 999,999.99 in cents
        $this->assertInstanceOf(\Wio\WioPayments\DTOs\PaymentResponse::class, $response);
    }

    /** @test */
    public function it_handles_processPayment_exception_with_previous_exception(): void
    {
        $originalException = new \Exception('Network timeout');
        
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/create-payment', Mockery::type('array'))
            ->andThrow($originalException);
            
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment processing failed: Network timeout');
        
        $this->wioPayments->charge('USD', 1000);
    }

    /** @test */
    public function it_handles_webhook_verification_with_future_timestamp(): void
    {
        $payload = '{"event": "test"}';
        $futureTime = time() + 301; // 301 seconds in future
        $signature = hash_hmac('sha256', $payload . $futureTime, 'test_secret_1234567890');
        
        // Should fail for future timestamp beyond threshold
        $result = $this->wioPayments->verifyWebhookSignature($payload, $signature, (string)$futureTime);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_webhook_verification_at_exact_boundary(): void
    {
        $payload = '{"event": "test"}';
        $currentTime = time();
        
        // Test at exact 300 second boundary (should pass)
        $boundaryTime = $currentTime - 300;
        $signature = hash_hmac('sha256', $payload . $boundaryTime, 'test_secret_1234567890');
        $result = $this->wioPayments->verifyWebhookSignature($payload, $signature, (string)$boundaryTime);
        $this->assertTrue($result);
        
        // Test at exact +300 second boundary (should pass)
        $futureBoundaryTime = $currentTime + 300;
        $futureSignature = hash_hmac('sha256', $payload . $futureBoundaryTime, 'test_secret_1234567890');
        $result = $this->wioPayments->verifyWebhookSignature($payload, $futureSignature, (string)$futureBoundaryTime);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_invalid_json_in_webhook_handler(): void
    {
        $invalidJsonPayload = '{invalid json syntax';
        $timestamp = time();
        $signature = hash_hmac('sha256', $invalidJsonPayload . $timestamp, 'test_secret_1234567890');
        
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid webhook payload format');
        
        $this->wioPayments->handleWebhook($invalidJsonPayload, $signature, (string)$timestamp);
    }

    /** @test */
    public function it_handles_else_path_in_checkout_session_expiration(): void
    {
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/checkout/sessions', Mockery::on(function ($data) {
                // Check that expires_at is set to current time + 1800 when not provided
                return isset($data['expires_at']) && 
                       $data['expires_at'] >= time() + 1799 && 
                       $data['expires_at'] <= time() + 1801;
            }))
            ->andReturn(['id' => 'cs_123', 'url' => 'https://checkout.example.com']);
            
        // Don't provide expires_at to trigger the else path
        $response = $this->wioPayments->createCheckoutSession('USD', 1000, [
            'success_url' => 'https://example.com/success'
        ]);
        
        $this->assertArrayHasKey('id', $response);
    }

    /** @test */
    public function it_handles_customer_validation_edge_cases(): void
    {
        // Test email validation false case
        $this->httpClientMock
            ->shouldReceive('post')
            ->never();
            
        $this->expectException(\Wio\WioPayments\Exceptions\InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email address');
        
        $this->wioPayments->createCustomer([
            'email' => 'invalid-email-format' // This should fail filter_var validation
        ]);
    }

    /** @test */
    public function it_handles_updateCustomer_email_validation(): void
    {
        $this->httpClientMock
            ->shouldReceive('put')
            ->never();
            
        $this->expectException(\Wio\WioPayments\Exceptions\InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email address');
        
        $this->wioPayments->updateCustomer('cus_123', [
            'email' => 'not-valid-email' // This should fail filter_var validation
        ]);
    }
}