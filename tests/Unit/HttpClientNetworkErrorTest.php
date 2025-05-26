<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\Support\HttpClient;
use Wio\WioPayments\Exceptions\PaymentFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;

class HttpClientNetworkErrorTest extends TestCase
{
    private HttpClient $httpClient;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new Client(['handler' => $handlerStack]);
        
        $this->httpClient = new HttpClient('https://test.api/', 'test_key', 'test_secret');
        
        // Inject mock client using reflection
        $reflection = new \ReflectionClass($this->httpClient);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->httpClient, $guzzleClient);
    }


    /** @test */
    public function it_handles_request_exception_with_response(): void
    {
        $errorResponse = ['error' => 'Unauthorized', 'code' => 401];
        $response = new Response(401, [], json_encode($errorResponse));
        $request = new Request('GET', 'test');
        
        $this->mockHandler->append(
            new ClientException('Unauthorized', $request, $response)
        );

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_handles_request_exception_without_response(): void
    {
        $request = new Request('PUT', 'test');
        $this->mockHandler->append(
            new RequestException('Request failed', $request)
        );

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Network error: Request failed');

        $this->httpClient->put('/test-endpoint', []);
    }

    /** @test */
    public function it_handles_malformed_json_response(): void
    {
        // Return invalid JSON that will fail json_decode
        $this->mockHandler->append(new Response(200, [], '{invalid json'));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_handles_http_error_with_no_body(): void
    {
        // HTTP error with empty body
        $this->mockHandler->append(new Response(500, [], ''));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('API Error (500): Unknown API error');

        $this->httpClient->post('/test-endpoint', []);
    }

    /** @test */
    public function it_handles_http_error_with_invalid_json_body(): void
    {
        // HTTP error with invalid JSON in body
        $this->mockHandler->append(new Response(400, [], '{malformed json'));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('API Error (400): Unknown API error');

        $this->httpClient->get('/test-endpoint');
    }

    /** @test */
    public function it_prioritizes_message_over_error_in_response(): void
    {
        $errorResponse = ['error' => 'Secondary Error', 'message' => 'Primary Message'];
        $this->mockHandler->append(new Response(422, [], json_encode($errorResponse)));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Primary Message');

        $this->httpClient->post('/test-endpoint', []);
    }

    /** @test */
    public function it_uses_error_when_no_message_in_response(): void
    {
        $errorResponse = ['error' => 'Only Error Available'];
        $this->mockHandler->append(new Response(404, [], json_encode($errorResponse)));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Only Error Available');

        $this->httpClient->get('/test-endpoint');
    }
}