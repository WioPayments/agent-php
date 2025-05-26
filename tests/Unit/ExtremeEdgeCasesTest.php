<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\WioPayments;
use Wio\WioPayments\DTOs\PaymentResponse;
use Wio\WioPayments\Exceptions\InvalidCredentialsException;
use Wio\WioPayments\Exceptions\InvalidCurrencyException;
use Wio\WioPayments\Exceptions\PaymentFailedException;

class ExtremeEdgeCasesTest extends TestCase
{
    /** @test */
    public function it_handles_null_parameter_in_currency_validation(): void
    {
        $this->expectException(\TypeError::class);
        
        // This will trigger TypeError due to null being passed to string parameter
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        $wioPayments->charge(null, 1000);
    }

    /** @test */
    public function it_handles_zero_payment_value_edge_case(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');
        
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        $wioPayments->charge('USD', 0);
    }

    /** @test */
    public function it_handles_negative_payment_value(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');
        
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        $wioPayments->charge('USD', -1000);
    }

    /** @test */
    public function it_handles_maximum_amount_boundary(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount exceeds maximum allowed limit');
        
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        $wioPayments->charge('USD', 100000000); // Exactly at boundary + 1
    }

    /** @test */
    public function it_handles_maximum_amount_minus_one(): void
    {
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        // This should NOT throw exception (99,999,999 cents = $999,999.99)
        try {
            $wioPayments->charge('USD', 99999999);
            $this->assertTrue(true); // If we get here, validation passed
        } catch (InvalidCurrencyException $e) {
            $this->fail('Amount 99999999 should be valid');
        } catch (\Exception $e) {
            // Expected to fail due to HTTP mock, but validation should pass
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /** @test */
    public function it_handles_unexpected_enum_string_values(): void
    {
        // Test PaymentResponse with unexpected status
        $response = PaymentResponse::fromArray([
            'id' => 'pay_test',
            'status' => 'unexpected_status_value',
            'currency' => 'USD',
            'amount' => 1000
        ]);

        // These should all return false for unexpected status
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertFalse($response->isFailed());
    }

    /** @test */
    public function it_handles_duplicate_operation_idempotency(): void
    {
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        // Test webhook signature with same payload twice
        $payload = '{"event": "payment.succeeded", "id": "pay_123"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', $payload . $timestamp, 'test_secret_1234567890');

        // First call
        $result1 = $wioPayments->verifyWebhookSignature($payload, $signature, (string)$timestamp);
        $this->assertTrue($result1);

        // Second call with same data (idempotent)
        $result2 = $wioPayments->verifyWebhookSignature($payload, $signature, (string)$timestamp);
        $this->assertTrue($result2);
    }

    /** @test */
    public function it_handles_empty_string_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('API key and secret key are required');
        
        new WioPayments('', '');
    }

    /** @test */
    public function it_handles_whitespace_only_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('API key and secret key are required');
        
        new WioPayments('   ', '   ');
    }

    /** @test */
    public function it_handles_past_dated_webhook_timestamp(): void
    {
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        $payload = '{"event": "test"}';
        $pastTimestamp = time() - 400; // 6+ minutes ago (beyond 5-minute limit)
        $signature = hash_hmac('sha256', $payload . $pastTimestamp, 'test_secret_1234567890');

        $result = $wioPayments->verifyWebhookSignature($payload, $signature, (string)$pastTimestamp);
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_future_dated_webhook_timestamp(): void
    {
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        $payload = '{"event": "test"}';
        $futureTimestamp = time() + 400; // 6+ minutes in future
        $signature = hash_hmac('sha256', $payload . $futureTimestamp, 'test_secret_1234567890');

        $result = $wioPayments->verifyWebhookSignature($payload, $signature, (string)$futureTimestamp);
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_invalid_json_in_webhook_payload(): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid webhook payload format');
        
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        $invalidPayload = '{"incomplete": json'; // Invalid JSON
        $timestamp = time();
        $signature = hash_hmac('sha256', $invalidPayload . $timestamp, 'test_secret_1234567890');

        $wioPayments->handleWebhook($invalidPayload, $signature, (string)$timestamp);
    }

    /** @test */
    public function it_handles_empty_webhook_payload(): void
    {
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        $result = $wioPayments->verifyWebhookSignature('', 'any_signature');
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_empty_webhook_signature(): void
    {
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        $result = $wioPayments->verifyWebhookSignature('{"event": "test"}', '');
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_currency_formatting_with_unknown_currency(): void
    {
        // Test formatAmount with unknown currency - should use currency code as symbol
        $formatted = WioPayments::formatAmount(1500, 'XYZ');
        $this->assertEquals('XYZ15.00', $formatted);
    }

    /** @test */
    public function it_handles_currency_formatting_with_lowercase_unknown(): void
    {
        // Test formatAmount with lowercase unknown currency
        $formatted = WioPayments::formatAmount(2500, 'abc');
        $this->assertEquals('ABC25.00', $formatted);
    }

    /** @test */
    public function it_handles_zero_amount_conversion_edge_cases(): void
    {
        // Test edge cases in amount conversion
        $this->assertEquals(0, WioPayments::toCents(0.0));
        $this->assertEquals(0.0, WioPayments::fromCents(0));
        
        // Test tiny amounts
        $this->assertEquals(1, WioPayments::toCents(0.01));
        $this->assertEquals(0.01, WioPayments::fromCents(1));
    }

    /** @test */
    public function it_handles_rounding_edge_cases_in_amount_conversion(): void
    {
        // Test rounding behavior
        $this->assertEquals(100, WioPayments::toCents(0.999)); // Should round to 100 cents
        $this->assertEquals(99, WioPayments::toCents(0.994));  // Should round to 99 cents
        $this->assertEquals(100, WioPayments::toCents(0.995)); // Should round to 100 cents
    }

    /** @test */
    public function it_handles_large_number_precision(): void
    {
        // Test large numbers don't lose precision
        $largeCents = 99999999;
        $amount = WioPayments::fromCents($largeCents);
        $backToCents = WioPayments::toCents($amount);
        
        $this->assertEquals($largeCents, $backToCents);
    }

    /** @test */
    public function it_handles_test_mode_toggle_multiple_times(): void
    {
        $wioPayments = new WioPayments('test_key_1234567890', 'test_secret_1234567890');
        
        // Test multiple toggles
        $this->assertFalse($wioPayments->isTestMode()); // Default false
        
        $wioPayments->setTestMode(true);
        $this->assertTrue($wioPayments->isTestMode());
        
        $wioPayments->setTestMode(false);
        $this->assertFalse($wioPayments->isTestMode());
        
        $wioPayments->setTestMode(true);
        $this->assertTrue($wioPayments->isTestMode());
    }

    /** @test */
    public function it_handles_payment_response_with_minimal_data(): void
    {
        // Test PaymentResponse with only required fields
        $response = PaymentResponse::fromArray([
            'id' => 'pay_minimal',
            'status' => 'pending'
            // No currency, amount, etc.
        ]);

        $this->assertEquals('pay_minimal', $response->id);
        $this->assertEquals('pending', $response->status);
        $this->assertNull($response->currency);
        $this->assertNull($response->amount);
        $this->assertTrue($response->isPending());
    }

    /** @test */
    public function it_handles_payment_response_array_conversion_with_nulls(): void
    {
        $response = PaymentResponse::fromArray([
            'id' => 'pay_test',
            'status' => 'completed'
            // Missing optional fields
        ]);

        $array = $response->toArray();
        
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('currency', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertNull($array['currency']);
        $this->assertNull($array['amount']);
    }
}