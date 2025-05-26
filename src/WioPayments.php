<?php

declare(strict_types=1);

namespace Wio\WioPayments;

use Wio\WioPayments\Contracts\PaymentInterface;
use Wio\WioPayments\DTOs\PaymentRequest;
use Wio\WioPayments\DTOs\PaymentResponse;
use Wio\WioPayments\Exceptions\InvalidCredentialsException;
use Wio\WioPayments\Exceptions\InvalidCurrencyException;
use Wio\WioPayments\Exceptions\PaymentFailedException;
use Wio\WioPayments\Support\Currency;
use Wio\WioPayments\Support\HttpClient;

class WioPayments implements PaymentInterface
{
    private const API_VERSION = 'v1';
    private const DEFAULT_BASE_URL = 'https://gw.wiopayments.com/api/';

    private string $apiKey;
    private string $secretKey;
    private HttpClient $httpClient;
    private bool $testMode = false;

    public function __construct(
        string $apiKey,
        string $secretKey,
        ?string $baseUrl = null
    ) {
        $this->validateCredentials($apiKey, $secretKey);
        
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->httpClient = new HttpClient(
            $baseUrl ?? self::DEFAULT_BASE_URL,
            $this->apiKey,
            $this->secretKey
        );
    }

    /**
     * Process a payment charge
     *
     * @param string $currency ISO 4217 currency code (USD, EUR, TRY, etc.)
     * @param int $amountInCents Amount in smallest currency unit (cents)
     * @param array $metadata Optional metadata for the payment
     * @throws InvalidCurrencyException
     * @throws PaymentFailedException
     */
    public function charge(
        string $currency,
        int $amountInCents,
        array $metadata = []
    ): PaymentResponse {
        $this->validateCurrency($currency);
        $this->validateAmount($amountInCents);

        $paymentRequest = new PaymentRequest(
            currency: strtoupper($currency),
            amount: $amountInCents,
            metadata: $metadata
        );

        return $this->processPayment($paymentRequest);
    }

    /**
     * Create a payment intent (for client-side completion)
     */
    public function createPaymentIntent(
        string $currency,
        int $amountInCents,
        array $options = []
    ): PaymentResponse {
        $this->validateCurrency($currency);
        $this->validateAmount($amountInCents);

        $data = [
            'currency' => strtoupper($currency),
            'amount' => $amountInCents,
            'confirm' => false,
            ...$options
        ];

        $response = $this->httpClient->post('/payment-intents', $data);
        
        return PaymentResponse::fromArray($response);
    }

    /**
     * Retrieve payment information
     */
    public function getPayment(string $paymentId): PaymentResponse
    {
        $response = $this->httpClient->get("/payments/{$paymentId}");
        
        return PaymentResponse::fromArray($response);
    }

    /**
     * Process a refund
     */
    public function refund(
        string $paymentId,
        ?int $amountInCents = null,
        array $metadata = []
    ): PaymentResponse {
        $data = [
            'payment_id' => $paymentId,
            'metadata' => $metadata
        ];

        if ($amountInCents !== null) {
            $this->validateAmount($amountInCents);
            $data['amount'] = $amountInCents;
        }

        $response = $this->httpClient->post('/refunds', $data);
        
        return PaymentResponse::fromArray($response);
    }

    /**
     * List payments with pagination and filtering
     */
    public function listPayments(array $filters = []): array
    {
        $queryParams = [];
        
        // Pagination
        if (isset($filters['page'])) {
            $queryParams['page'] = (int) $filters['page'];
        }
        if (isset($filters['limit'])) {
            $queryParams['limit'] = min((int) $filters['limit'], 100); // Max 100 per page
        }
        
        // Status filter
        if (isset($filters['status'])) {
            $queryParams['status'] = $filters['status'];
        }
        
        // Date range filters
        if (isset($filters['start_date'])) {
            $queryParams['start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date'])) {
            $queryParams['end_date'] = $filters['end_date'];
        }
        
        // Currency filter
        if (isset($filters['currency'])) {
            $queryParams['currency'] = strtoupper($filters['currency']);
        }
        
        // Customer filter
        if (isset($filters['customer_id'])) {
            $queryParams['customer_id'] = $filters['customer_id'];
        }
        
        // Amount range filters
        if (isset($filters['min_amount'])) {
            $queryParams['min_amount'] = (int) $filters['min_amount'];
        }
        if (isset($filters['max_amount'])) {
            $queryParams['max_amount'] = (int) $filters['max_amount'];
        }
        
        $url = '/payments' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
        
        return $this->httpClient->get($url);
    }

    /**
     * Get payments by date range
     */
    public function getPaymentsByDateRange(
        string $startDate,
        string $endDate,
        array $options = []
    ): array {
        $queryParams = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Additional filters
        if (isset($options['status'])) {
            $queryParams['status'] = $options['status'];
        }
        if (isset($options['currency'])) {
            $queryParams['currency'] = strtoupper($options['currency']);
        }
        if (isset($options['limit'])) {
            $queryParams['limit'] = min((int) $options['limit'], 100);
        }
        if (isset($options['page'])) {
            $queryParams['page'] = (int) $options['page'];
        }
        
        $url = '/payments/date-range?' . http_build_query($queryParams);
        
        return $this->httpClient->get($url);
    }

    /**
     * Get payment statistics and metrics
     */
    public function getPaymentStatistics(array $filters = []): array
    {
        $queryParams = [];
        
        // Date range
        if (isset($filters['start_date'])) {
            $queryParams['start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date'])) {
            $queryParams['end_date'] = $filters['end_date'];
        }
        
        // Grouping options
        if (isset($filters['group_by'])) {
            $allowedGroupBy = ['day', 'week', 'month', 'year', 'currency', 'status'];
            if (in_array($filters['group_by'], $allowedGroupBy)) {
                $queryParams['group_by'] = $filters['group_by'];
            }
        }
        
        // Currency filter
        if (isset($filters['currency'])) {
            $queryParams['currency'] = strtoupper($filters['currency']);
        }
        
        $url = '/payments/statistics' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
        
        return $this->httpClient->get($url);
    }

    /**
     * Verify webhook signature for security
     */
    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $timestamp = null
    ): bool {
        if (empty($payload) || empty($signature)) {
            return false;
        }
        
        // Extract timestamp if provided
        $providedTimestamp = $timestamp ?? time();
        
        // Check if timestamp is within acceptable range (5 minutes)
        $currentTimestamp = time();
        if (abs($currentTimestamp - $providedTimestamp) > 300) {
            return false;
        }
        
        // Create expected signature
        $expectedSignature = hash_hmac('sha256', $payload . $providedTimestamp, $this->secretKey);
        
        // Compare signatures using timing-safe comparison
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle incoming webhook event
     */
    public function handleWebhook(
        string $payload,
        string $signature,
        string $timestamp = null
    ): array {
        if (!$this->verifyWebhookSignature($payload, $signature, $timestamp)) {
            throw new PaymentFailedException('Invalid webhook signature');
        }
        
        $data = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PaymentFailedException('Invalid webhook payload format');
        }
        
        return $data;
    }

    /**
     * Create secure checkout session URL
     */
    public function createCheckoutSession(
        string $currency,
        int $amountInCents,
        array $options = []
    ): array {
        $this->validateCurrency($currency);
        $this->validateAmount($amountInCents);
        
        $data = [
            'currency' => strtoupper($currency),
            'amount' => $amountInCents,
            'mode' => 'payment'
        ];
        
        // Success and cancel URLs
        if (isset($options['success_url'])) {
            $data['success_url'] = $options['success_url'];
        }
        if (isset($options['cancel_url'])) {
            $data['cancel_url'] = $options['cancel_url'];
        }
        
        // Customer information
        if (isset($options['customer_id'])) {
            $data['customer_id'] = $options['customer_id'];
        }
        if (isset($options['customer_email'])) {
            $data['customer_email'] = $options['customer_email'];
        }
        
        // Payment methods
        if (isset($options['payment_methods'])) {
            $data['payment_methods'] = $options['payment_methods'];
        }
        
        // Metadata
        if (isset($options['metadata'])) {
            $data['metadata'] = $options['metadata'];
        }
        
        // Expiration time (default 30 minutes)
        if (isset($options['expires_at'])) {
            $data['expires_at'] = $options['expires_at'];
        } else {
            $data['expires_at'] = time() + 1800; // 30 minutes
        }
        
        return $this->httpClient->post('/checkout/sessions', $data);
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $customerData): array
    {
        $data = [];
        
        // Required fields
        if (isset($customerData['email'])) {
            $data['email'] = filter_var($customerData['email'], FILTER_VALIDATE_EMAIL);
            if (!$data['email']) {
                throw new InvalidCredentialsException('Invalid email address');
            }
        }
        
        // Optional fields
        if (isset($customerData['name'])) {
            $data['name'] = trim($customerData['name']);
        }
        if (isset($customerData['phone'])) {
            $data['phone'] = $customerData['phone'];
        }
        if (isset($customerData['address'])) {
            $data['address'] = $customerData['address'];
        }
        if (isset($customerData['metadata'])) {
            $data['metadata'] = $customerData['metadata'];
        }
        
        return $this->httpClient->post('/customers', $data);
    }

    /**
     * Get customer information
     */
    public function getCustomer(string $customerId): array
    {
        return $this->httpClient->get("/customers/{$customerId}");
    }

    /**
     * Update customer information
     */
    public function updateCustomer(string $customerId, array $updateData): array
    {
        $data = [];
        
        // Allowed update fields
        $allowedFields = ['name', 'email', 'phone', 'address', 'metadata'];
        
        foreach ($allowedFields as $field) {
            if (isset($updateData[$field])) {
                if ($field === 'email') {
                    $email = filter_var($updateData[$field], FILTER_VALIDATE_EMAIL);
                    if (!$email) {
                        throw new InvalidCredentialsException('Invalid email address');
                    }
                    $data[$field] = $email;
                } else {
                    $data[$field] = $updateData[$field];
                }
            }
        }
        
        return $this->httpClient->put("/customers/{$customerId}", $data);
    }

    /**
     * List all customers with pagination
     */
    public function listCustomers(array $filters = []): array
    {
        $queryParams = [];
        
        if (isset($filters['page'])) {
            $queryParams['page'] = (int) $filters['page'];
        }
        if (isset($filters['limit'])) {
            $queryParams['limit'] = min((int) $filters['limit'], 100);
        }
        if (isset($filters['email'])) {
            $queryParams['email'] = $filters['email'];
        }
        if (isset($filters['created_after'])) {
            $queryParams['created_after'] = $filters['created_after'];
        }
        
        $url = '/customers' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
        
        return $this->httpClient->get($url);
    }

    /**
     * Get payments for a specific customer
     */
    public function getCustomerPayments(string $customerId, array $filters = []): array
    {
        $queryParams = [];
        
        // Pagination
        if (isset($filters['page'])) {
            $queryParams['page'] = (int) $filters['page'];
        }
        if (isset($filters['limit'])) {
            $queryParams['limit'] = min((int) $filters['limit'], 100);
        }
        
        // Status filter
        if (isset($filters['status'])) {
            $queryParams['status'] = $filters['status'];
        }
        
        // Date range
        if (isset($filters['start_date'])) {
            $queryParams['start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date'])) {
            $queryParams['end_date'] = $filters['end_date'];
        }
        
        // Currency filter
        if (isset($filters['currency'])) {
            $queryParams['currency'] = strtoupper($filters['currency']);
        }
        
        $url = "/customers/{$customerId}/payments" . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
        
        return $this->httpClient->get($url);
    }

    /**
     * Cancel a pending payment
     */
    public function cancelPayment(string $paymentId, array $options = []): PaymentResponse
    {
        $data = [
            'cancellation_reason' => $options['reason'] ?? 'requested_by_customer'
        ];
        
        // Add metadata if provided
        if (isset($options['metadata'])) {
            $data['metadata'] = $options['metadata'];
        }
        
        $response = $this->httpClient->post("/payments/{$paymentId}/cancel", $data);
        
        return PaymentResponse::fromArray($response);
    }

    /**
     * Capture an authorized payment
     */
    public function capturePayment(string $paymentId, ?int $amountInCents = null): PaymentResponse
    {
        $data = [];
        
        if ($amountInCents !== null) {
            $this->validateAmount($amountInCents);
            $data['amount'] = $amountInCents;
        }
        
        $response = $this->httpClient->post("/payments/{$paymentId}/capture", $data);
        
        return PaymentResponse::fromArray($response);
    }

    /**
     * Validate API credentials
     */
    public function validateApiCredentials(): array
    {
        try {
            $response = $this->httpClient->get('/account/verify');
            
            return [
                'valid' => true,
                'account_info' => $response
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account information
     */
    public function getAccountInfo(): array
    {
        return $this->httpClient->get('/account');
    }

    /**
     * Format amount with currency
     */
    public static function formatAmount(int $amountInCents, string $currency): string
    {
        $amount = $amountInCents / 100;
        
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'TRY' => '₺',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$'
        ];
        
        $symbol = $symbols[strtoupper($currency)] ?? strtoupper($currency);
        
        // JPY doesn't use decimal places
        if (strtoupper($currency) === 'JPY') {
            return $symbol . number_format($amountInCents);
        }
        
        return $symbol . number_format($amount, 2);
    }

    /**
     * Convert amount to cents
     */
    public static function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert cents to amount
     */
    public static function fromCents(int $amountInCents): float
    {
        return $amountInCents / 100;
    }

    /**
     * Get supported currencies
     */
    public static function getSupportedCurrencies(): array
    {
        return Currency::getSupportedCurrencies();
    }

    /**
     * Check if currency is supported
     */
    public static function isCurrencySupported(string $currency): bool
    {
        return Currency::isSupported($currency);
    }

    /**
     * Enable test mode
     */
    public function setTestMode(bool $enabled = true): self
    {
        $this->testMode = $enabled;
        
        return $this;
    }

    /**
     * Check if test mode is enabled
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Create test payment for sandbox
     */
    public function createTestPayment(
        string $currency = 'USD',
        int $amountInCents = 1000,
        string $scenario = 'success'
    ): PaymentResponse {
        if (!$this->testMode) {
            throw new PaymentFailedException('Test mode must be enabled to create test payments');
        }
        
        $data = [
            'currency' => strtoupper($currency),
            'amount' => $amountInCents,
            'test_scenario' => $scenario, // success, failure, timeout
            'test_mode' => true
        ];
        
        $response = $this->httpClient->post('/test/payments', $data);
        
        return PaymentResponse::fromArray($response);
    }

    /**
     * Simulate webhook event for testing
     */
    public function simulateWebhook(string $eventType, array $data = []): array
    {
        if (!$this->testMode) {
            throw new PaymentFailedException('Test mode must be enabled to simulate webhooks');
        }
        
        $payload = [
            'event_type' => $eventType,
            'test_mode' => true,
            'data' => $data
        ];
        
        return $this->httpClient->post('/test/webhooks', $payload);
    }

    /**
     * Get account balance
     */
    public function getBalance(): array
    {
        return $this->httpClient->get('/balance');
    }

    private function processPayment(PaymentRequest $request): PaymentResponse
    {
        try {
            $response = $this->httpClient->post('/create-payment', $request->toArray());
            
            return PaymentResponse::fromArray($response);
        } catch (\Exception $e) {
            throw new PaymentFailedException(
                "Payment processing failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    private function validateCredentials(string $apiKey, string $secretKey): void
    {
        if (empty(trim($apiKey)) || empty(trim($secretKey))) {
            throw new InvalidCredentialsException('API key and secret key are required');
        }

        if (strlen($apiKey) < 10 || strlen($secretKey) < 10) {
            throw new InvalidCredentialsException('Invalid API key or secret key format');
        }
    }

    private function validateCurrency(string $currency): void
    {
        if (!Currency::isSupported($currency)) {
            throw new InvalidCurrencyException(
                "Unsupported currency: {$currency}. Supported currencies: " . 
                implode(', ', Currency::getSupportedCurrencies())
            );
        }
    }

    private function validateAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidCurrencyException('Amount must be greater than zero');
        }

        if ($amount > 99999999) { // 999,999.99 in cents
            throw new InvalidCurrencyException('Amount exceeds maximum allowed limit');
        }
    }
}