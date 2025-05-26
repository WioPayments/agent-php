<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use Wio\WioPayments\WioPayments;
use Wio\WioPayments\Support\HttpClient;
use Wio\WioPayments\DTOs\PaymentResponse;
use Wio\WioPayments\Exceptions\InvalidCredentialsException;
use Wio\WioPayments\Exceptions\InvalidCurrencyException;
use Wio\WioPayments\Exceptions\PaymentFailedException;

class WioPaymentsTest extends TestCase
{
    private MockInterface $httpClientMock;
    private WioPayments $wioPayments;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpClientMock = Mockery::mock(HttpClient::class);
        
        // Create WioPayments instance with reflection to inject mock
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
    public function it_throws_exception_for_empty_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('API key and secret key are required');
        
        new WioPayments('', '');
    }

    /** @test */
    public function it_throws_exception_for_short_api_key(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid API key or secret key format');
        
        new WioPayments('short', 'valid_secret_key_here');
    }

    /** @test */
    public function it_throws_exception_for_short_secret_key(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid API key or secret key format');
        
        new WioPayments('valid_api_key_here', 'short');
    }

    /** @test */
    public function it_accepts_valid_credentials(): void
    {
        $wioPayments = new WioPayments(
            'valid_api_key_here',
            'valid_secret_key_here'
        );

        $this->assertInstanceOf(WioPayments::class, $wioPayments);
    }

    /** @test */
    public function it_validates_invalid_currency(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        
        $this->wioPayments->charge('INVALID', 1000);
    }

    /** @test */
    public function it_validates_zero_amount(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');
        
        $this->wioPayments->charge('USD', 0);
    }

    /** @test */
    public function it_validates_negative_amount(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');
        
        $this->wioPayments->charge('USD', -100);
    }

    /** @test */
    public function it_validates_amount_exceeds_maximum(): void
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('Amount exceeds maximum allowed limit');
        
        $this->wioPayments->charge('USD', 100000000); // Exceeds 99,999,999
    }

    /** @test */
    public function it_processes_successful_charge(): void
    {
        $mockResponse = [
            'id' => 'pay_123456789',
            'status' => 'completed',
            'amount' => 2999,
            'currency' => 'USD'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/create-payment', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->charge('USD', 2999);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_creates_payment_intent(): void
    {
        $mockResponse = [
            'id' => 'pi_123456789',
            'status' => 'requires_payment_method',
            'amount' => 5000,
            'currency' => 'EUR'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payment-intents', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createPaymentIntent('EUR', 5000);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_retrieves_payment(): void
    {
        $mockResponse = [
            'id' => 'pay_123456789',
            'status' => 'completed',
            'amount' => 2999,
            'currency' => 'USD'
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/payments/pay_123456789')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getPayment('pay_123456789');

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_processes_full_refund(): void
    {
        $mockResponse = [
            'id' => 'ref_123456789',
            'status' => 'succeeded',
            'amount' => 2999,
            'payment_id' => 'pay_123456789'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/refunds', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->refund('pay_123456789');

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_processes_partial_refund(): void
    {
        $mockResponse = [
            'id' => 'ref_123456789',
            'status' => 'succeeded',
            'amount' => 1000,
            'payment_id' => 'pay_123456789'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/refunds', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->refund('pay_123456789', 1000);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_lists_payments_with_filters(): void
    {
        $mockResponse = [
            'data' => [
                ['id' => 'pay_1', 'status' => 'completed'],
                ['id' => 'pay_2', 'status' => 'completed']
            ],
            'pagination' => ['page' => 1, 'total' => 2]
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::pattern('/^\/payments\?/'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->listPayments([
            'status' => 'completed',
            'page' => 1,
            'limit' => 50
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
    }

    /** @test */
    public function it_gets_payments_by_date_range(): void
    {
        $mockResponse = [
            'data' => [
                ['id' => 'pay_1', 'created_at' => '2024-01-15'],
                ['id' => 'pay_2', 'created_at' => '2024-01-20']
            ]
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::pattern('/^\/payments\/date-range\?/'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getPaymentsByDateRange(
            '2024-01-01',
            '2024-01-31'
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
    }

    /** @test */
    public function it_gets_payment_statistics(): void
    {
        $mockResponse = [
            'total_amount' => 150000,
            'total_count' => 50,
            'success_rate' => 95.5,
            'average_amount' => 3000
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::pattern('/^\/payments\/statistics/'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getPaymentStatistics([
            'start_date' => '2024-01-01',
            'group_by' => 'month'
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('total_amount', $response);
    }

    /** @test */
    public function it_verifies_valid_webhook_signature(): void
    {
        $payload = '{"event": "payment.succeeded"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', $payload . $timestamp, 'test_secret_key_12345');

        $result = $this->wioPayments->verifyWebhookSignature($payload, $signature, (string)$timestamp);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_invalid_webhook_signature(): void
    {
        $payload = '{"event": "payment.succeeded"}';
        $timestamp = time();
        $invalidSignature = 'invalid_signature';

        $result = $this->wioPayments->verifyWebhookSignature($payload, $invalidSignature, (string)$timestamp);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_expired_webhook_timestamp(): void
    {
        $payload = '{"event": "payment.succeeded"}';
        $expiredTimestamp = time() - 400; // 6+ minutes ago
        $signature = hash_hmac('sha256', $payload . $expiredTimestamp, 'test_secret_key_12345');

        $result = $this->wioPayments->verifyWebhookSignature($payload, $signature, (string)$expiredTimestamp);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_valid_webhook(): void
    {
        $payload = '{"event": "payment.succeeded", "data": {"id": "pay_123"}}';
        $timestamp = time();
        $signature = hash_hmac('sha256', $payload . $timestamp, 'test_secret_key_12345');

        $result = $this->wioPayments->handleWebhook($payload, $signature, (string)$timestamp);

        $this->assertIsArray($result);
        $this->assertEquals('payment.succeeded', $result['event']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_webhook_signature(): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $payload = '{"event": "payment.succeeded"}';
        $invalidSignature = 'invalid_signature';

        $this->wioPayments->handleWebhook($payload, $invalidSignature);
    }

    /** @test */
    public function it_throws_exception_for_invalid_webhook_payload(): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Invalid webhook payload format');

        $invalidPayload = 'invalid json';
        $timestamp = time();
        $signature = hash_hmac('sha256', $invalidPayload . $timestamp, 'test_secret_key_12345');

        $this->wioPayments->handleWebhook($invalidPayload, $signature, (string)$timestamp);
    }

    /** @test */
    public function it_creates_checkout_session(): void
    {
        $mockResponse = [
            'id' => 'cs_123456789',
            'url' => 'https://checkout.wiopayments.com/cs_123456789',
            'expires_at' => time() + 1800
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/checkout/sessions', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createCheckoutSession('USD', 2999, [
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel'
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('url', $response);
    }

    /** @test */
    public function it_creates_customer(): void
    {
        $mockResponse = [
            'id' => 'cus_123456789',
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/customers', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createCustomer([
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
    }

    /** @test */
    public function it_validates_customer_email(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email address');

        $this->wioPayments->createCustomer([
            'email' => 'invalid-email',
            'name' => 'Test User'
        ]);
    }

    /** @test */
    public function it_gets_customer(): void
    {
        $mockResponse = [
            'id' => 'cus_123456789',
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/customers/cus_123456789')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getCustomer('cus_123456789');

        $this->assertIsArray($response);
        $this->assertEquals('cus_123456789', $response['id']);
    }

    /** @test */
    public function it_updates_customer(): void
    {
        $mockResponse = [
            'id' => 'cus_123456789',
            'email' => 'updated@example.com',
            'name' => 'Updated Name'
        ];

        $this->httpClientMock
            ->shouldReceive('put')
            ->once()
            ->with('/customers/cus_123456789', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->updateCustomer('cus_123456789', [
            'email' => 'updated@example.com',
            'name' => 'Updated Name'
        ]);

        $this->assertIsArray($response);
        $this->assertEquals('updated@example.com', $response['email']);
    }

    /** @test */
    public function it_lists_customers(): void
    {
        $mockResponse = [
            'data' => [
                ['id' => 'cus_1', 'email' => 'user1@example.com'],
                ['id' => 'cus_2', 'email' => 'user2@example.com']
            ]
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::pattern('/^\/customers/'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->listCustomers(['page' => 1, 'limit' => 50]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
    }

    /** @test */
    public function it_gets_customer_payments(): void
    {
        $mockResponse = [
            'data' => [
                ['id' => 'pay_1', 'customer_id' => 'cus_123'],
                ['id' => 'pay_2', 'customer_id' => 'cus_123']
            ]
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::pattern('/^\/customers\/cus_123\/payments/'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getCustomerPayments('cus_123');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
    }

    /** @test */
    public function it_cancels_payment(): void
    {
        $mockResponse = [
            'id' => 'pay_123456789',
            'status' => 'canceled',
            'cancellation_reason' => 'requested_by_customer'
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payments/pay_123456789/cancel', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->cancelPayment('pay_123456789');

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_captures_payment(): void
    {
        $mockResponse = [
            'id' => 'pay_123456789',
            'status' => 'captured',
            'amount' => 2999
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payments/pay_123456789/capture', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->capturePayment('pay_123456789');

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_validates_api_credentials(): void
    {
        $mockResponse = [
            'account_id' => 'acc_123',
            'business_name' => 'Test Business'
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/account/verify')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->validateApiCredentials();

        $this->assertTrue($response['valid']);
        $this->assertArrayHasKey('account_info', $response);
    }

    /** @test */
    public function it_handles_credential_validation_failure(): void
    {
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/account/verify')
            ->andThrow(new \Exception('Unauthorized'));

        $response = $this->wioPayments->validateApiCredentials();

        $this->assertFalse($response['valid']);
        $this->assertArrayHasKey('error', $response);
    }

    /** @test */
    public function it_gets_account_info(): void
    {
        $mockResponse = [
            'account_id' => 'acc_123',
            'business_name' => 'Test Business',
            'country' => 'US'
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/account')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getAccountInfo();

        $this->assertIsArray($response);
        $this->assertEquals('acc_123', $response['account_id']);
    }

    /** @test */
    public function it_gets_balance(): void
    {
        $mockResponse = [
            'available' => [
                ['amount' => 50000, 'currency' => 'USD']
            ],
            'pending' => [
                ['amount' => 10000, 'currency' => 'USD']
            ]
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/balance')
            ->andReturn($mockResponse);

        $response = $this->wioPayments->getBalance();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('available', $response);
    }

    /** @test */
    public function it_sets_test_mode(): void
    {
        $result = $this->wioPayments->setTestMode(true);

        $this->assertInstanceOf(WioPayments::class, $result);
        $this->assertTrue($this->wioPayments->isTestMode());
    }

    /** @test */
    public function it_creates_test_payment(): void
    {
        $this->wioPayments->setTestMode(true);

        $mockResponse = [
            'id' => 'test_pay_123',
            'status' => 'succeeded',
            'test_mode' => true
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/test/payments', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->createTestPayment('USD', 1000, 'success');

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_throws_exception_when_creating_test_payment_without_test_mode(): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Test mode must be enabled to create test payments');

        $this->wioPayments->createTestPayment();
    }

    /** @test */
    public function it_simulates_webhook(): void
    {
        $this->wioPayments->setTestMode(true);

        $mockResponse = [
            'event_id' => 'evt_test_123',
            'delivered' => true
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/test/webhooks', Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->wioPayments->simulateWebhook('payment.succeeded', ['payment_id' => 'pay_123']);

        $this->assertIsArray($response);
        $this->assertTrue($response['delivered']);
    }

    /** @test */
    public function it_throws_exception_when_simulating_webhook_without_test_mode(): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Test mode must be enabled to simulate webhooks');

        $this->wioPayments->simulateWebhook('payment.succeeded');
    }

    /** @test */
    public function it_formats_amount_with_currency(): void
    {
        $this->assertEquals('$29.99', WioPayments::formatAmount(2999, 'USD'));
        $this->assertEquals('€50.00', WioPayments::formatAmount(5000, 'EUR'));
        $this->assertEquals('₺100.00', WioPayments::formatAmount(10000, 'TRY'));
        $this->assertEquals('¥1,000', WioPayments::formatAmount(1000, 'JPY'));
    }

    /** @test */
    public function it_converts_amount_to_cents(): void
    {
        $this->assertEquals(2999, WioPayments::toCents(29.99));
        $this->assertEquals(5000, WioPayments::toCents(50.00));
        $this->assertEquals(1000, WioPayments::toCents(10.00));
    }

    /** @test */
    public function it_converts_cents_to_amount(): void
    {
        $this->assertEquals(29.99, WioPayments::fromCents(2999));
        $this->assertEquals(50.00, WioPayments::fromCents(5000));
        $this->assertEquals(10.00, WioPayments::fromCents(1000));
    }

    /** @test */
    public function it_checks_currency_support(): void
    {
        $this->assertTrue(WioPayments::isCurrencySupported('USD'));
        $this->assertTrue(WioPayments::isCurrencySupported('EUR'));
        $this->assertTrue(WioPayments::isCurrencySupported('TRY'));
        $this->assertFalse(WioPayments::isCurrencySupported('INVALID'));
    }

    /** @test */
    public function it_gets_supported_currencies(): void
    {
        $currencies = WioPayments::getSupportedCurrencies();

        $this->assertIsArray($currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertContains('TRY', $currencies);
    }

    /** @test */
    public function it_throws_payment_failed_exception_on_charge_error(): void
    {
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->andThrow(new \Exception('Network error'));

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment processing failed: Network error');

        $this->wioPayments->charge('USD', 1000);
    }
}