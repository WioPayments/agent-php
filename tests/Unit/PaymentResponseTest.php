<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\DTOs\PaymentResponse;

class PaymentResponseTest extends TestCase
{
    /** @test */
    public function it_creates_from_array(): void
    {
        $data = [
            'id' => 'pay_123456789',
            'status' => 'completed',
            'amount' => 2999,
            'currency' => 'USD',
            'created_at' => '2024-01-15T10:30:00Z'
        ];

        $response = PaymentResponse::fromArray($data);

        $this->assertInstanceOf(PaymentResponse::class, $response);
        $this->assertEquals('pay_123456789', $response->id);
        $this->assertEquals('completed', $response->status);
        $this->assertEquals(2999, $response->amount);
        $this->assertEquals('USD', $response->currency);
    }

    /** @test */
    public function it_checks_if_successful(): void
    {
        $successfulResponse = PaymentResponse::fromArray([
            'id' => 'pay_123',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'USD'
        ]);

        $failedResponse = PaymentResponse::fromArray([
            'id' => 'pay_456',
            'status' => 'failed',
            'amount' => 1000,
            'currency' => 'USD'
        ]);

        $this->assertTrue($successfulResponse->isSuccessful());
        $this->assertFalse($failedResponse->isSuccessful());
    }

    /** @test */
    public function it_checks_if_pending(): void
    {
        $pendingResponse = PaymentResponse::fromArray([
            'id' => 'pay_123',
            'status' => 'pending',
            'amount' => 1000,
            'currency' => 'USD'
        ]);

        $completedResponse = PaymentResponse::fromArray([
            'id' => 'pay_456',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'USD'
        ]);

        $this->assertTrue($pendingResponse->isPending());
        $this->assertFalse($completedResponse->isPending());
    }

    /** @test */
    public function it_checks_if_failed(): void
    {
        $failedResponse = PaymentResponse::fromArray([
            'id' => 'pay_123',
            'status' => 'failed',
            'amount' => 1000,
            'currency' => 'USD'
        ]);

        $successfulResponse = PaymentResponse::fromArray([
            'id' => 'pay_456',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'USD'
        ]);

        $this->assertTrue($failedResponse->isFailed());
        $this->assertFalse($successfulResponse->isFailed());
    }

    /** @test */
    public function it_converts_to_array(): void
    {
        $originalData = [
            'id' => 'pay_123456789',
            'status' => 'completed',
            'amount' => 2999,
            'currency' => 'USD',
            'metadata' => ['order_id' => 'ORD-001']
        ];

        $response = PaymentResponse::fromArray($originalData);
        $arrayData = $response->toArray();

        $this->assertIsArray($arrayData);
        $this->assertEquals($originalData['id'], $arrayData['id']);
        $this->assertEquals($originalData['status'], $arrayData['status']);
        $this->assertEquals($originalData['amount'], $arrayData['amount']);
    }

    /** @test */
    public function it_handles_missing_optional_fields(): void
    {
        $minimalData = [
            'id' => 'pay_123',
            'status' => 'pending'
        ];

        $response = PaymentResponse::fromArray($minimalData);

        $this->assertEquals('pay_123', $response->id);
        $this->assertEquals('pending', $response->status);
        $this->assertNull($response->amount ?? null);
        $this->assertNull($response->currency ?? null);
    }
}