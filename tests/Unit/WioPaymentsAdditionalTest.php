<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use Wio\WioPayments\WioPayments;
use Wio\WioPayments\Support\HttpClient;
use Wio\WioPayments\DTOs\PaymentResponse;
use Wio\WioPayments\Exceptions\InvalidCurrencyException;
use Wio\WioPayments\Exceptions\PaymentFailedException;

class WioPaymentsAdditionalTest extends TestCase
{
    private MockInterface $httpClientMock;
    private WioPayments $wioPayments;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpClientMock = Mockery::mock(HttpClient::class);
        $this->wioPayments = new WioPayments(
            'test_api_key_12345',
            'test_secret_key_12345'
        );
        
        // Use reflection to replace httpClient with mock
        $reflection = new \ReflectionClass($this->wioPayments);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->wioPayments, $this->httpClientMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_validates_refund_amount(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');
        
        $this->wioPayments->refund('pay_123', 0);
    }

    /** @test */
    public function it_validates_capture_amount(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount exceeds maximum allowed limit');
        
        $this->wioPayments->capturePayment('pay_123', 100000000);
    }

    /** @test */
    public function it_creates_payment_intent_with_confirmation(): void
    {
        $mockResponse = [
            'id' => 'pi_test_123',
            'status' => 'requires_confirmation',
            'amount' => 1500,
            'currency' => 'USD'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payment-intents', Mockery::on(function ($data) {
                return $data['confirm'] === false && 
                       $data['amount'] === 1500 && 
                       $data['currency'] === 'USD';
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createPaymentIntent('USD', 1500, [
            'automatic_payment_methods' => ['enabled' => true]
        ]);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_creates_checkout_session_with_all_options(): void
    {
        $mockResponse = [
            'id' => 'cs_test_123',
            'url' => 'https://checkout.wiopayments.com/cs_test_123',
            'expires_at' => time() + 1800
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/checkout/sessions', Mockery::on(function ($data) {
                return $data['currency'] === 'EUR' &&
                       $data['amount'] === 5000 &&
                       $data['success_url'] === 'https://example.com/success' &&
                       $data['customer_email'] === 'test@example.com' &&
                       isset($data['expires_at']);
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createCheckoutSession('EUR', 5000, [
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'customer_email' => 'test@example.com',
            'payment_methods' => ['card', 'bank_transfer'],
            'metadata' => ['order_id' => 'ORD-12345']
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('url', $response);
    }

    /** @test */
    public function it_validates_checkout_session_currency(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        
        $this->wioPayments->createCheckoutSession('INVALID', 1000);
    }

    /** @test */
    public function it_validates_checkout_session_amount(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');
        
        $this->wioPayments->createCheckoutSession('USD', -500);
    }

    /** @test */
    public function it_creates_customer_with_all_fields(): void
    {
        $mockResponse = [
            'id' => 'cus_test_123',
            'email' => 'customer@example.com',
            'name' => 'John Doe',
            'phone' => '+1234567890',
            'address' => ['city' => 'New York']
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/customers', Mockery::on(function ($data) {
                return $data['email'] === 'customer@example.com' &&
                       $data['name'] === 'John Doe' &&
                       $data['phone'] === '+1234567890' &&
                       isset($data['address']) &&
                       isset($data['metadata']);
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createCustomer([
            'email' => 'customer@example.com',
            'name' => 'John Doe',
            'phone' => '+1234567890',
            'address' => ['city' => 'New York'],
            'metadata' => ['source' => 'website']
        ]);

        $this->assertIsArray($response);
        $this->assertEquals('cus_test_123', $response['id']);
    }

    /** @test */
    public function it_validates_customer_email_in_update(): void
    {
        $this->expectException(\Wio\WioPayments\Exceptions\InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email address');

        $this->wioPayments->updateCustomer('cus_123', [
            'email' => 'not-an-email'
        ]);
    }

    /** @test */
    public function it_lists_customers_with_all_filters(): void
    {
        $mockResponse = [
            'data' => [
                ['id' => 'cus_1', 'email' => 'user1@example.com'],
                ['id' => 'cus_2', 'email' => 'user2@example.com']
            ],
            'pagination' => ['page' => 2, 'total' => 50]
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/customers?page=2&limit=25&email=test%40example.com&created_after=2024-01-01')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->listCustomers([
            'page' => 2,
            'limit' => 25,
            'email' => 'test@example.com',
            'created_after' => '2024-01-01'
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('pagination', $response);
    }

    /** @test */
    public function it_gets_customer_payments_with_all_filters(): void
    {
        $mockResponse = [
            'data' => [
                ['id' => 'pay_1', 'customer_id' => 'cus_123', 'status' => 'completed'],
                ['id' => 'pay_2', 'customer_id' => 'cus_123', 'status' => 'completed']
            ]
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::on(function ($url) {
                return str_contains($url, '/customers/cus_123/payments') &&
                       str_contains($url, 'status=completed') &&
                       str_contains($url, 'currency=USD') &&
                       str_contains($url, 'start_date=2024-01-01') &&
                       str_contains($url, 'end_date=2024-01-31');
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getCustomerPayments('cus_123', [
            'status' => 'completed',
            'currency' => 'USD',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'page' => 1,
            'limit' => 20
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
    }

    /** @test */
    public function it_cancels_payment_with_custom_reason_and_metadata(): void
    {
        $mockResponse = [
            'id' => 'pay_123',
            'status' => 'canceled',
            'cancellation_reason' => 'duplicate_charge'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payments/pay_123/cancel', Mockery::on(function ($data) {
                return $data['cancellation_reason'] === 'duplicate_charge' &&
                       isset($data['metadata']) &&
                       $data['metadata']['admin_notes'] === 'Duplicate detected';
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->cancelPayment('pay_123', [
            'reason' => 'duplicate_charge',
            'metadata' => ['admin_notes' => 'Duplicate detected']
        ]);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_captures_partial_payment_amount(): void
    {
        $mockResponse = [
            'id' => 'pay_123',
            'status' => 'captured',
            'amount' => 1500
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payments/pay_123/capture', Mockery::on(function ($data) {
                return $data['amount'] === 1500;
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->capturePayment('pay_123', 1500);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_handles_webhook_with_empty_payload(): void
    {
        $result = $this->wioPayments->verifyWebhookSignature('', 'some_signature');
        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_webhook_with_empty_signature(): void
    {
        $payload = '{"event": "test"}';
        
        $result = $this->wioPayments->verifyWebhookSignature($payload, '');
        $this->assertFalse($result);
    }

    /** @test */
    public function it_disables_test_mode(): void
    {
        $this->wioPayments->setTestMode(true);
        $this->assertTrue($this->wioPayments->isTestMode());
        
        $this->wioPayments->setTestMode(false);
        $this->assertFalse($this->wioPayments->isTestMode());
    }

    /** @test */
    public function it_creates_test_payment_with_failure_scenario(): void
    {
        $this->wioPayments->setTestMode(true);

        $mockResponse = [
            'id' => 'test_pay_failed',
            'status' => 'failed',
            'test_mode' => true,
            'failure_code' => 'card_declined'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/test/payments', Mockery::on(function ($data) {
                return $data['test_scenario'] === 'failure' &&
                       $data['currency'] === 'EUR' &&
                       $data['amount'] === 2000;
            }))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createTestPayment('EUR', 2000, 'failure');

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_formats_unknown_currency_with_symbol(): void
    {
        $formatted = WioPayments::formatAmount(1500, 'ZAR');
        $this->assertEquals('ZAR15.00', $formatted);
    }

    /** @test */
    public function it_handles_large_amount_conversion(): void
    {
        $cents = WioPayments::toCents(999999.99);
        $this->assertEquals(99999999, $cents);
        
        $amount = WioPayments::fromCents(99999999);
        $this->assertEquals(999999.99, $amount);
    }
}