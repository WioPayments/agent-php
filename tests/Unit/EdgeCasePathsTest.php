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

class EdgeCasePathsTest extends TestCase
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
    public function it_handles_invalid_group_by_filter_in_statistics(): void
    {
        $mockResponse = ['total' => 0];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/payments/statistics')
            ->andReturn($mockResponse);

        // Test INVALID group_by value - should be ignored
        $response = $this->wioPayments->getPaymentStatistics([
            'group_by' => 'invalid_group_option' // This path is uncovered
        ]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_empty_query_params_in_statistics(): void
    {
        $mockResponse = ['total' => 0];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/payments/statistics') // No query string when empty
            ->andReturn($mockResponse);

        // Test empty filters - triggers (!empty($queryParams) ? ...) false path
        $response = $this->wioPayments->getPaymentStatistics([]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_date_range_payments_with_all_optional_filters(): void
    {
        $mockResponse = ['data' => []];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/payments/date-range?start_date=2024-01-01&end_date=2024-01-31&status=failed&currency=EUR&limit=100&page=3')
            ->andReturn($mockResponse);

        // Test ALL optional filter paths in getPaymentsByDateRange
        $response = $this->wioPayments->getPaymentsByDateRange(
            '2024-01-01',
            '2024-01-31',
            [
                'status' => 'failed',
                'currency' => 'eur', // lowercase to test strtoupper path
                'limit' => 150, // over 100, tests min() function
                'page' => 3
            ]
        );

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_webhook_signature_with_string_timestamp(): void
    {
        $payload = '{"event": "test"}';
        $timestamp = (string) (time() - 100); // Valid timestamp as string
        $signature = hash_hmac('sha256', $payload . $timestamp, 'test_secret_key_12345');

        // This tests the string timestamp path
        $result = $this->wioPayments->verifyWebhookSignature($payload, $signature, $timestamp);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_webhook_signature_without_timestamp(): void
    {
        $payload = '{"event": "test"}';
        $currentTime = time();
        $signature = hash_hmac('sha256', $payload . $currentTime, 'test_secret_key_12345');

        // This tests the ($timestamp ?? time()) null path
        $result = $this->wioPayments->verifyWebhookSignature($payload, $signature);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_payment_intent_with_empty_options(): void
    {
        $mockResponse = ['id' => 'pi_test', 'status' => 'requires_payment_method'];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payment-intents', Mockery::on(function ($data) {
                // Should have confirm: false and basic fields only
                return $data['confirm'] === false && 
                       $data['currency'] === 'USD' && 
                       $data['amount'] === 1000 &&
                       count($data) === 3; // Only basic fields
            }))
            ->andReturn($mockResponse);

        // Test empty options path in createPaymentIntent
        $response = $this->wioPayments->createPaymentIntent('USD', 1000, []);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_handles_checkout_session_without_expires_at(): void
    {
        $mockResponse = ['id' => 'cs_test', 'url' => 'https://test.com'];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/checkout/sessions', Mockery::on(function ($data) {
                // Should have auto-generated expires_at
                return isset($data['expires_at']) && 
                       $data['expires_at'] > time() &&
                       $data['expires_at'] <= time() + 1800;
            }))
            ->andReturn($mockResponse);

        // Test auto expires_at path (else branch)
        $response = $this->wioPayments->createCheckoutSession('USD', 1000, [
            'success_url' => 'https://example.com/success'
            // No expires_at provided
        ]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_customer_creation_without_email(): void
    {
        $mockResponse = ['id' => 'cus_test', 'name' => 'Test User'];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/customers', Mockery::on(function ($data) {
                // Should only have name, no email
                return !isset($data['email']) && 
                       $data['name'] === 'Test User';
            }))
            ->andReturn($mockResponse);

        // Test no email path in createCustomer
        $response = $this->wioPayments->createCustomer([
            'name' => 'Test User'
            // No email provided
        ]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_update_customer_with_disallowed_fields(): void
    {
        $mockResponse = ['id' => 'cus_test', 'name' => 'Updated'];

        $this->httpClientMock
            ->shouldReceive('put')
            ->once()
            ->with('/customers/cus_test', Mockery::on(function ($data) {
                // Should only have allowed fields, ignore invalid ones
                return isset($data['name']) && 
                       !isset($data['invalid_field']) &&
                       count($data) === 1;
            }))
            ->andReturn($mockResponse);

        // Test field filtering in updateCustomer
        $response = $this->wioPayments->updateCustomer('cus_test', [
            'name' => 'Updated',
            'invalid_field' => 'should_be_ignored', // Not in allowedFields
            'another_invalid' => 'also_ignored'
        ]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_list_payments_with_max_limit(): void
    {
        $mockResponse = ['data' => []];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::on(function ($url) {
                // Should cap limit at 100
                return str_contains($url, 'limit=100');
            }))
            ->andReturn($mockResponse);

        // Test min() function path for limit > 100
        $response = $this->wioPayments->listPayments([
            'limit' => 999 // Should be capped at 100
        ]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_list_customers_with_max_limit(): void
    {
        $mockResponse = ['data' => []];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::on(function ($url) {
                return str_contains($url, 'limit=100');
            }))
            ->andReturn($mockResponse);

        // Test min() function path in listCustomers
        $response = $this->wioPayments->listCustomers([
            'limit' => 500 // Should be capped at 100
        ]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_customer_payments_with_max_limit(): void
    {
        $mockResponse = ['data' => []];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::on(function ($url) {
                return str_contains($url, '/customers/cus_test/payments') &&
                       str_contains($url, 'limit=100');
            }))
            ->andReturn($mockResponse);

        // Test min() function path in getCustomerPayments
        $response = $this->wioPayments->getCustomerPayments('cus_test', [
            'limit' => 250 // Should be capped at 100
        ]);

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_cancel_payment_without_metadata(): void
    {
        $mockResponse = ['id' => 'pay_test', 'status' => 'canceled'];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payments/pay_test/cancel', Mockery::on(function ($data) {
                // Should only have cancellation_reason, no metadata
                return $data['cancellation_reason'] === 'requested_by_customer' &&
                       !isset($data['metadata']) &&
                       count($data) === 1;
            }))
            ->andReturn($mockResponse);

        // Test no metadata path in cancelPayment
        $response = $this->wioPayments->cancelPayment('pay_test', []);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_handles_capture_payment_without_amount(): void
    {
        $mockResponse = ['id' => 'pay_test', 'status' => 'captured'];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/payments/pay_test/capture', Mockery::on(function ($data) {
                // Should be empty array when no amount provided
                return empty($data);
            }))
            ->andReturn($mockResponse);

        // Test null amount path in capturePayment
        $response = $this->wioPayments->capturePayment('pay_test', null);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_handles_refund_without_amount(): void
    {
        $mockResponse = ['id' => 'ref_test', 'status' => 'succeeded'];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/refunds', Mockery::on(function ($data) {
                // Should not have amount field when null
                return $data['payment_id'] === 'pay_test' &&
                       !isset($data['amount']) &&
                       isset($data['metadata']);
            }))
            ->andReturn($mockResponse);

        // Test null amount path in refund
        $response = $this->wioPayments->refund('pay_test', null, ['note' => 'full refund']);

        $this->assertInstanceOf(PaymentResponse::class, $response);
    }

    /** @test */
    public function it_handles_test_mode_false_setting(): void
    {
        // Start with test mode enabled
        $this->wioPayments->setTestMode(true);
        $this->assertTrue($this->wioPayments->isTestMode());
        
        // Test setting to false
        $result = $this->wioPayments->setTestMode(false);
        
        $this->assertInstanceOf(WioPayments::class, $result);
        $this->assertFalse($this->wioPayments->isTestMode());
    }
}