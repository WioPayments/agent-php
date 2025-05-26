<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\Support\HttpClient;

class HttpClientSimpleTest extends TestCase
{
    /** @test */
    public function it_creates_instance_correctly(): void
    {
        $httpClient = new HttpClient(
            'https://api.test.com',
            'test_api_key',
            'test_secret_key'
        );

        $this->assertInstanceOf(HttpClient::class, $httpClient);
    }

    /** @test */
    public function it_has_required_public_methods(): void
    {
        $httpClient = new HttpClient(
            'https://api.test.com',
            'test_api_key',
            'test_secret_key'
        );

        $this->assertTrue(method_exists($httpClient, 'get'));
        $this->assertTrue(method_exists($httpClient, 'post'));
        $this->assertTrue(method_exists($httpClient, 'put'));
    }
}