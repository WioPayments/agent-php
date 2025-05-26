<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\WioPayments;
use Wio\WioPayments\Support\HttpClient;
use Mockery;

class Perfect100CoverageTest extends TestCase
{
    private WioPayments $wioPayments;
    private $httpClientMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClientMock = Mockery::mock(HttpClient::class);
        $this->wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
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
    public function it_covers_listPayments_missing_filters(): void
    {
        $mockResponse = ['data' => [], 'total' => 0];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/payments?start_date=2024-01-01&end_date=2024-01-31&currency=EUR&customer_id=cus_123&min_amount=1000&max_amount=5000')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->listPayments([
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31', 
            'currency' => 'eur',
            'customer_id' => 'cus_123',
            'min_amount' => 1000,
            'max_amount' => 5000
        ]);

        $this->assertArrayHasKey('data', $response);
    }

    /** @test */
    public function it_covers_getPaymentStatistics_missing_filters(): void
    {
        $mockResponse = ['total' => 0];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/payments/statistics?end_date=2024-01-31&currency=USD')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getPaymentStatistics([
            'end_date' => '2024-01-31',
            'currency' => 'usd'
        ]);

        $this->assertArrayHasKey('total', $response);
    }

    /** @test */
    public function it_covers_createCheckoutSession_missing_options(): void
    {
        $mockResponse = ['id' => 'cs_123'];
        $expiresAt = time() + 3600;

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/checkout/sessions', Mockery::on(function ($data) use ($expiresAt) {
                return isset($data['customer_id']) && isset($data['expires_at']);
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createCheckoutSession('USD', 1000, [
            'customer_id' => 'cus_456',
            'expires_at' => $expiresAt
        ]);

        $this->assertEquals('cs_123', $response['id']);
    }
}