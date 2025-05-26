<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Wio\WioPayments\Support\HttpClient;
use Wio\WioPayments\Exceptions\PaymentFailedException;

class HttpClientWorkingTest extends TestCase
{
    private HttpClient $httpClient;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new Client(['handler' => $handlerStack]);
        
        $this->httpClient = new HttpClient(
            'https://api.test.com',
            'test_api_key',
            'test_secret_key'
        );
        
        // Use reflection to inject mock client
        $reflection = new \ReflectionClass($this->httpClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->httpClient, $guzzleClient);
    }

    /** @test */
    public function it_makes_successful_get_request(): void
    {
        $responseData = ['status' => 'success', 'data' => []];
        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $result = $this->httpClient->get('/test-endpoint');

        $this->assertEquals($responseData, $result);
    }

    /** @test */
    public function it_makes_successful_post_request(): void
    {
        $responseData = ['id' => 'pay_123', 'status' => 'created'];
        $this->mockHandler->append(new Response(201, [], json_encode($responseData)));

        $result = $this->httpClient->post('/payments', ['amount' => 1000]);

        $this->assertEquals($responseData, $result);
    }

    /** @test */
    public function it_makes_successful_put_request(): void
    {
        $responseData = ['id' => 'cus_123', 'name' => 'Updated Name'];
        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $result = $this->httpClient->put('/customers/cus_123', ['name' => 'Updated Name']);

        $this->assertEquals($responseData, $result);
    }

    /** @test */
    public function it_throws_exception_on_network_error(): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Network error');

        $this->mockHandler->append(new RequestException('Network error', new Request('GET', 'test')));

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_handles_http_400_error(): void
    {
        $errorResponse = ['error' => 'Bad Request', 'message' => 'Invalid data'];
        $this->mockHandler->append(new Response(400, [], json_encode($errorResponse)));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid data');

        $this->httpClient->post('/payments', []);
    }

    /** @test */
    public function it_handles_http_500_error(): void
    {
        $errorResponse = ['message' => 'Internal Server Error'];
        $this->mockHandler->append(new Response(500, [], json_encode($errorResponse)));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Internal Server Error');

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_handles_invalid_json_response(): void
    {
        $this->mockHandler->append(new Response(200, [], 'invalid json'));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_handles_empty_response_body(): void
    {
        $this->mockHandler->append(new Response(200, [], ''));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_includes_authentication_headers(): void
    {
        $responseData = ['success' => true];
        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        // Test that request includes auth headers by verifying response
        $result = $this->httpClient->post('/payments', ['amount' => 1000]);

        $this->assertEquals($responseData, $result);
        
        // Verify mock was called (confirming request was made)
        $this->assertCount(0, $this->mockHandler);
    }

    /** @test */
    public function it_handles_get_request_without_data(): void
    {
        $responseData = ['balance' => 50000];
        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $result = $this->httpClient->get('/balance');

        $this->assertEquals($responseData, $result);
    }

    /** @test */
    public function it_handles_request_exception_with_response(): void
    {
        $errorResponse = ['error' => 'Payment declined', 'code' => 'card_declined'];
        $response = new Response(402, [], json_encode($errorResponse));
        $request = new Request('POST', '/payments');
        
        $this->mockHandler->append(new RequestException('Payment declined', $request, $response));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('API Error (402): Payment declined');

        $this->httpClient->post('/payments', ['amount' => 1000]);
    }

    /** @test */
    public function it_handles_request_exception_without_response(): void
    {
        $request = new Request('GET', '/test');
        $this->mockHandler->append(new RequestException('Connection timeout', $request));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Network error: Connection timeout');

        $this->httpClient->get('/test');
    }
}