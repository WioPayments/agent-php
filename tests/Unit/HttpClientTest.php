<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Wio\WioPayments\Support\HttpClient;
use Wio\WioPayments\Exceptions\PaymentFailedException;

class HttpClientTest extends TestCase
{
    private $guzzleMock;
    private HttpClient $httpClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->guzzleMock = Mockery::mock(Client::class);
        $this->httpClient = new HttpClient(
            'https://api.test.com',
            'test_api_key',
            'test_secret_key'
        );
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->httpClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->httpClient, $this->guzzleMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_makes_successful_get_request(): void
    {
        $responseData = ['status' => 'success', 'data' => []];
        $response = new Response(200, [], json_encode($responseData));

        $this->guzzleMock
            ->shouldReceive('request')
            ->once()
            ->with('GET', 'test-endpoint', Mockery::type('array'))
            ->andReturn($response);

        $result = $this->httpClient->get('/test-endpoint');

        $this->assertEquals($responseData, $result);
    }

    /** @test */
    public function it_makes_successful_post_request(): void
    {
        $requestData = ['amount' => 1000, 'currency' => 'USD'];
        $responseData = ['id' => 'pay_123', 'status' => 'created'];
        $response = new Response(201, [], json_encode($responseData));

        $this->guzzleMock
            ->shouldReceive('request')
            ->once()
            ->with('POST', 'payments', Mockery::type('array'))
            ->andReturn($response);

        $result = $this->httpClient->post('/payments', $requestData);

        $this->assertEquals($responseData, $result);
    }

    /** @test */
    public function it_makes_successful_put_request(): void
    {
        $requestData = ['name' => 'Updated Name'];
        $responseData = ['id' => 'cus_123', 'name' => 'Updated Name'];
        $response = new Response(200, [], json_encode($responseData));

        $this->guzzleMock
            ->shouldReceive('request')
            ->once()
            ->with('PUT', 'customers/cus_123', Mockery::type('array'))
            ->andReturn($response);

        $result = $this->httpClient->put('/customers/cus_123', $requestData);

        $this->assertEquals($responseData, $result);
    }

    /** @test */
    public function it_throws_exception_on_request_error(): void
    {
        $this->expectException(PaymentFailedException::class);

        $this->guzzleMock
            ->shouldReceive('request')
            ->once()
            ->andThrow(new RequestException('Network error', Mockery::mock('Psr\Http\Message\RequestInterface')));

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_handles_invalid_json_response(): void
    {
        $response = new Response(200, [], 'invalid json');

        $this->guzzleMock
            ->shouldReceive('request')
            ->once()
            ->andReturn($response);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_handles_http_error_status(): void
    {
        $errorResponse = ['error' => 'Payment failed', 'code' => 'card_declined'];
        $response = new Response(400, [], json_encode($errorResponse));

        $this->guzzleMock
            ->shouldReceive('request')
            ->once()
            ->andReturn($response);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment failed');

        $this->httpClient->post('/payments', []);
    }
}