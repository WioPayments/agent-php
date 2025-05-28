# WioPayments PHP SDK

[![Latest Stable Version](https://img.shields.io/packagist/v/wio/wiopayments.svg?style=flat-square)](https://packagist.org/packages/wio/wiopayments)
[![Test Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat-square)](https://github.com/wio/wiopayments/actions)
[![PHP Version Require](https://img.shields.io/packagist/php-v/wio/wiopayments.svg?style=flat-square)](https://php.net)
[![Total Downloads](https://img.shields.io/packagist/dt/wio/wiopayments.svg?style=flat-square)](https://packagist.org/packages/wio/wiopayments)
[![License](https://img.shields.io/packagist/l/wio/wiopayments.svg?style=flat-square)](LICENSE.md)

**WioPayments PHP SDK** is the official, enterprise-grade payment processing library for PHP applications. Built with modern PHP standards and comprehensive test coverage, it provides a secure and reliable integration with the WioPayments Gateway platform.

## Features

- ✅ **100% Test Coverage** - Enterprise-grade reliability
- 🔒 **Security-First Design** - Built-in signature verification and validation
- 🚀 **Modern PHP 8.1+** - Leveraging latest PHP features and performance
- 📦 **Framework Agnostic** - Works with Laravel, Symfony, or any PHP application
- 💳 **Comprehensive Payment Support** - Cards, digital wallets, and alternative payment methods
- 🌍 **Multi-Currency** - Support for major global currencies
- 📊 **Advanced Analytics** - Detailed payment statistics and reporting
- 🔄 **Idempotent Operations** - Safe retry mechanisms for network failures
- 📚 **Complete Documentation** - Extensive examples and API reference

## System Requirements

- **PHP**: 8.1 or higher
- **Dependencies**: cURL extension, JSON extension
- **Framework**: Laravel 10.0+ (optional), Symfony 6.0+ (optional)
- **Memory**: Minimum 64MB PHP memory limit
- **SSL**: TLS 1.2 or higher for secure communications

## Installation

Install the SDK using Composer:

```bash
composer require wio/wiopayments
```

For Laravel applications, the service provider will be automatically registered via package discovery.

## Configuration

### Environment Variables

Add your WioPayments credentials to your `.env` file:

```env
WIOPAYMENTS_API_KEY=wio_live_sk_...
WIOPAYMENTS_SECRET_KEY=wio_live_whsec_...
WIOPAYMENTS_TIMEOUT=30
```

### Basic Initialization

```php
<?php

use Wio\WioPayments\WioPayments;

$wioPayments = new WioPayments(
    apiKey: env('WIOPAYMENTS_API_KEY'),
    secretKey: env('WIOPAYMENTS_SECRET_KEY')
);
```

## Quick Start Guide

### Processing Your First Payment

```php
<?php

use Wio\WioPayments\WioPayments;
use Wio\WioPayments\Exceptions\PaymentFailedException;

try {
    $wioPayments = new WioPayments(
        apiKey: 'your_api_key',
        secretKey: 'your_secret_key'
    );

    $paymentResponse = $wioPayments->charge(
        currency: 'USD',
        amountInCents: 2999, // $29.99
        metadata: [
            'customer_id' => 'cust_12345',
            'order_id' => 'order_67890',
            'description' => 'Premium subscription'
        ]
    );

    if ($paymentResponse->isSuccessful()) {
        // Payment completed successfully
        $paymentId = $paymentResponse->id;
        $transactionFee = $paymentResponse->fee;
        
        // Store payment information in your database
        // Send confirmation email to customer
        
        echo "Payment processed successfully. ID: {$paymentId}";
    } else {
        // Payment requires additional action or failed
        echo "Payment status: {$paymentResponse->status}";
    }

} catch (PaymentFailedException $e) {
    // Handle payment failures
    error_log("Payment failed: " . $e->getMessage());
    
    // Show user-friendly error message
    echo "We're unable to process your payment. Please try again.";
}
```

## Core API Reference

### Payment Operations

#### Create Payment Charge

```php
$response = $wioPayments->charge(
    currency: 'USD',
    amountInCents: 5000, // $50.00
    metadata: [
        'customer_email' => 'customer@example.com',
        'product_sku' => 'PROD-001'
    ]
);
```

#### Create Payment Intent (for client-side completion)

```php
$intent = $wioPayments->createPaymentIntent(
    currency: 'EUR',
    amountInCents: 2500, // €25.00
    options: [
        'payment_methods' => ['card', 'sepa_debit'],
        'customer_id' => 'cust_12345',
        'automatic_payment_methods' => true
    ]
);

// Return client_secret to frontend for completion
$clientSecret = $intent->client_secret;
```

#### Retrieve Payment Details

```php
$payment = $wioPayments->getPayment('pay_1234567890');

echo "Payment Amount: " . WioPayments::formatAmount($payment->amount, $payment->currency);
echo "Payment Status: " . $payment->status;
echo "Created: " . $payment->created_at;
```

#### Process Refunds

```php
// Full refund
$refundResponse = $wioPayments->refund(
    paymentId: 'pay_1234567890',
    metadata: ['reason' => 'customer_request']
);

// Partial refund
$partialRefund = $wioPayments->refund(
    paymentId: 'pay_1234567890',
    amountInCents: 1000, // $10.00 refund
    metadata: ['reason' => 'partial_return']
);
```

#### Cancel Pending Payments

```php
$cancellation = $wioPayments->cancelPayment(
    paymentId: 'pay_1234567890',
    options: [
        'reason' => 'requested_by_customer',
        'metadata' => ['cancelled_by' => 'customer_service']
    ]
);
```

### Customer Management

#### Create Customer Profile

```php
$customer = $wioPayments->createCustomer([
    'email' => 'john.doe@example.com',
    'name' => 'John Doe',
    'phone' => '+1-555-123-4567',
    'address' => [
        'line1' => '123 Main Street',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US'
    ],
    'metadata' => [
        'user_id' => '12345',
        'signup_date' => '2024-01-15'
    ]
]);

$customerId = $customer['id'];
```

#### Retrieve Customer Information

```php
$customerData = $wioPayments->getCustomer('cust_12345');
$customerPayments = $wioPayments->getCustomerPayments('cust_12345', [
    'limit' => 50,
    'status' => 'succeeded'
]);
```

#### Update Customer Details

```php
$updatedCustomer = $wioPayments->updateCustomer('cust_12345', [
    'email' => 'newemail@example.com',
    'metadata' => ['last_updated' => date('Y-m-d H:i:s')]
]);
```

### Advanced Payment Features

#### Checkout Sessions (Hosted Payment Pages)

```php
$checkoutSession = $wioPayments->createCheckoutSession(
    currency: 'USD',
    amountInCents: 4999, // $49.99
    options: [
        'success_url' => 'https://yourstore.com/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://yourstore.com/cancelled',
        'customer_email' => 'customer@example.com',
        'payment_methods' => ['card', 'klarna', 'afterpay'],
        'shipping_address_collection' => true,
        'expires_at' => time() + 3600, // 1 hour expiration
        'metadata' => [
            'order_id' => 'ORD-12345'
        ]
    ]
);

// Redirect customer to checkout
header("Location: " . $checkoutSession['url']);
```

#### Payment Analytics and Reporting

```php
// Get payment statistics
$statistics = $wioPayments->getPaymentStatistics([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'group_by' => 'day',
    'currency' => 'USD'
]);

// List payments with advanced filtering
$payments = $wioPayments->listPayments([
    'status' => 'succeeded',
    'currency' => 'USD',
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'customer_id' => 'cust_12345',
    'min_amount' => 1000, // $10.00 minimum
    'max_amount' => 10000, // $100.00 maximum
    'limit' => 100,
    'page' => 1
]);

// Get payments by date range
$rangePayments = $wioPayments->getPaymentsByDateRange(
    startDate: '2024-01-01',
    endDate: '2024-01-31',
    options: [
        'status' => 'succeeded',
        'currency' => 'USD'
    ]
);
```

### Webhook Security and Event Handling

#### Webhook Signature Verification

```php
<?php

// In your webhook endpoint (e.g., /webhook/wiopayments)
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_WIO_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_WIO_TIMESTAMP'] ?? '';

try {
    // Verify webhook authenticity
    $isValid = $wioPayments->verifyWebhookSignature(
        payload: $payload,
        signature: $signature,
        timestamp: $timestamp
    );

    if (!$isValid) {
        http_response_code(400);
        exit('Invalid webhook signature');
    }

    // Process verified webhook
    $event = $wioPayments->handleWebhook($payload, $signature, $timestamp);
    
    // Handle different event types
    switch ($event['type']) {
        case 'payment.succeeded':
            // Update order status, send confirmation email
            handleSuccessfulPayment($event['data']);
            break;
            
        case 'payment.failed':
            // Log failure, notify customer
            handleFailedPayment($event['data']);
            break;
            
        case 'refund.created':
            // Process refund, update inventory
            handleRefundCreated($event['data']);
            break;
    }

    http_response_code(200);
    echo 'Webhook processed successfully';

} catch (Exception $e) {
    error_log('Webhook processing failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Webhook processing failed');
}
```

### Currency and Localization

#### Supported Currencies

```php
// Check supported currencies
$supportedCurrencies = WioPayments::getSupportedCurrencies();
// Returns: ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'TRY', ...]

// Validate currency support
$isSupported = WioPayments::isCurrencySupported('TRY'); // true

// Format amounts with proper currency symbols
echo WioPayments::formatAmount(2999, 'USD'); // $29.99
echo WioPayments::formatAmount(2999, 'EUR'); // €29.99
echo WioPayments::formatAmount(2999, 'JPY'); // ¥2,999 (no decimals)
echo WioPayments::formatAmount(2999, 'TRY'); // ₺29.99
```

#### Amount Conversion Utilities

```php
// Convert between dollars and cents
$cents = WioPayments::toCents(29.99);   // 2999
$dollars = WioPayments::fromCents(2999); // 29.99

// Safe decimal handling for financial calculations
$totalCents = WioPayments::toCents(19.99) + WioPayments::toCents(5.00); // 2499
$totalDollars = WioPayments::fromCents($totalCents); // 24.99
```

### Development and Testing

#### Test Mode Configuration

```php
// Enable test mode for development
$wioPayments->setTestMode(true);

// Create test payments with specific scenarios
$testPayment = $wioPayments->createTestPayment(
    currency: 'USD',
    amountInCents: 1000,
    scenario: 'success' // 'success', 'failure', 'timeout'
);

// Simulate webhook events for testing
$webhookSimulation = $wioPayments->simulateWebhook(
    eventType: 'payment.succeeded',
    data: [
        'payment_id' => 'test_pay_123',
        'amount' => 1000,
        'currency' => 'USD'
    ]
);

// Check if in test mode
if ($wioPayments->isTestMode()) {
    echo "Running in test mode - no real charges will be made";
}
```

#### API Credential Validation

```php
$validation = $wioPayments->validateApiCredentials();

if ($validation['valid']) {
    $accountInfo = $validation['account_info'];
    echo "API connection successful";
    echo "Account ID: " . $accountInfo['account_id'];
    echo "Business Name: " . $accountInfo['business_name'];
} else {
    echo "API credential validation failed: " . $validation['error'];
}
```

#### Account Information and Balance

```php
// Get account information
$accountInfo = $wioPayments->getAccountInfo();
echo "Account Type: " . $accountInfo['account_type'];
echo "Business Name: " . $accountInfo['business_name'];

// Check account balance
$balance = $wioPayments->getBalance();
foreach ($balance['available'] as $currency) {
    echo "Available {$currency['currency']}: " . 
         WioPayments::formatAmount($currency['amount'], $currency['currency']);
}
```

## Error Handling and Exception Management

The SDK provides comprehensive error handling with specific exception types:

```php
use Wio\WioPayments\Exceptions\{
    InvalidCredentialsException,
    InvalidCurrencyException,
    PaymentFailedException,
    WioPaymentsException
};

try {
    $payment = $wioPayments->charge('USD', 2999);
    
} catch (InvalidCredentialsException $e) {
    // Authentication or authorization errors
    error_log('API credentials invalid: ' . $e->getMessage());
    
} catch (InvalidCurrencyException $e) {
    // Currency validation or amount errors
    error_log('Currency error: ' . $e->getMessage());
    
} catch (PaymentFailedException $e) {
    // Payment processing failures
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    
    // Log detailed error information
    error_log("Payment failed (Code: {$errorCode}): {$errorMessage}");
    
} catch (WioPaymentsException $e) {
    // General SDK errors
    error_log('WioPayments SDK error: ' . $e->getMessage());
    
} catch (Exception $e) {
    // Unexpected errors
    error_log('Unexpected error: ' . $e->getMessage());
}
```

## Laravel Integration

### Service Provider Registration

The package automatically registers with Laravel via package discovery. For manual registration:

```php
// config/app.php
'providers' => [
    // ...
    Wio\WioPayments\WioPaymentsServiceProvider::class,
],

'aliases' => [
    // ...
    'WioPayments' => Wio\WioPayments\Facades\WioPayments::class,
],
```

### Configuration Publishing

```bash
php artisan vendor:publish --provider="Wio\WioPayments\WioPaymentsServiceProvider"
```

### Using in Controllers

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Wio\WioPayments\WioPayments;
use Wio\WioPayments\Exceptions\PaymentFailedException;

class PaymentController extends Controller
{
    public function __construct(
        private WioPayments $wioPayments
    ) {}

    public function processPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.50',
            'currency' => 'required|string|size:3',
            'customer_email' => 'required|email'
        ]);

        try {
            $paymentResponse = $this->wioPayments->charge(
                currency: strtoupper($request->currency),
                amountInCents: WioPayments::toCents($request->amount),
                metadata: [
                    'customer_email' => $request->customer_email,
                    'user_id' => auth()->id(),
                    'ip_address' => $request->ip()
                ]
            );

            return response()->json([
                'success' => true,
                'payment_id' => $paymentResponse->id,
                'status' => $paymentResponse->status,
                'amount' => WioPayments::formatAmount(
                    $paymentResponse->amount, 
                    $paymentResponse->currency
                )
            ]);

        } catch (PaymentFailedException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Payment processing failed',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
```

### Using Facade

```php
use Wio\WioPayments\Facades\WioPayments;

// Process payment using facade
$response = WioPayments::charge('USD', 2999);

// Format currency
$formatted = WioPayments::formatAmount(2999, 'USD');
```

## Security Best Practices

1. **Environment Variables**: Never commit API keys to version control
2. **Webhook Verification**: Always verify webhook signatures
3. **Input Validation**: Validate all payment parameters
4. **Error Logging**: Log errors securely without exposing sensitive data
5. **HTTPS Only**: Use SSL/TLS for all API communications
6. **Rate Limiting**: Implement appropriate rate limiting for payment endpoints

## Testing and Quality Assurance

This SDK maintains **100% test coverage** with comprehensive unit and integration tests:

```bash
# Run the test suite
composer test

# Run tests with coverage report
composer test:coverage

# Run static analysis
composer analyze

# Run code quality checks
composer quality
```

## Version Compatibility

| SDK Version | PHP Version | Laravel Version | Support Status |
|-------------|-------------|-----------------|----------------|
| 1.x         | 8.1+        | 10.0+          | ✅ Active      |

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details on how to contribute to this project.

## Security Vulnerabilities

If you discover a security vulnerability within this SDK, please send an email to security@wiopayments.com. All security vulnerabilities will be promptly addressed.

## Support

- **Documentation**: [https://docs.wiopayments.com](https://docs.wiopayments.com)
- **API Reference**: [https://api.wiopayments.com/docs](https://api.wiopayments.com/docs)
- **Support Email**: support@wiopayments.com
- **GitHub Issues**: [Report Issues](https://github.com/wio/wiopayments/issues)

## License

The WioPayments PHP SDK is open-source software licensed under the [MIT license](LICENSE.md).

---

<p align="center">
<strong>Built with ❤️ by the WioPayments Team</strong><br>
<em>Secure payments made simple for developers worldwide</em>
</p>
