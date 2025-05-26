<?php

declare(strict_types=1);

namespace Wio\WioPayments\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Wio\WioPayments\Exceptions\PaymentFailedException;

class HttpClient
{
    private Client $client;
    private string $apiKey;
    private string $secretKey;

    public function __construct(string $baseUrl, string $apiKey, string $secretKey)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WioPayments-PHP/1.0.0',
            ]
        ]);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->makeRequest('POST', $endpoint, $data);
    }

    public function get(string $endpoint): array
    {
        return $this->makeRequest('GET', $endpoint);
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->makeRequest('PUT', $endpoint, $data);
    }

    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $options = [
                'headers' => $this->getAuthHeaders($data),
            ];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, ltrim($endpoint, '/'), $options);
            
            // Check for HTTP error status codes
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $body = $response->getBody()->getContents();
                $decoded = json_decode($body, true);
                
                $message = $decoded['message'] ?? $decoded['error'] ?? 'HTTP Error';
                throw new PaymentFailedException($message, $statusCode);
            }
            
            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new PaymentFailedException('Invalid JSON response');
            }

            return $decoded;
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }

    private function getAuthHeaders(array $data = []): array
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        
        // Create signature for request authentication
        $signatureData = $this->apiKey . $timestamp . $nonce . json_encode($data);
        $signature = hash_hmac('sha256', $signatureData, $this->secretKey);

        return [
            'X-API-Key' => $this->apiKey,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
        ];
    }

    private function handleRequestException(RequestException $e): never
    {
        $response = $e->getResponse();
        
        if ($response) {
            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);
            
            $message = $decoded['message'] ?? $decoded['error'] ?? 'Unknown API error';
            $code = $response->getStatusCode();
            
            throw new PaymentFailedException("API Error ({$code}): {$message}", $code, $e);
        }

        throw new PaymentFailedException("Network error: {$e->getMessage()}", 0, $e);
    }
}