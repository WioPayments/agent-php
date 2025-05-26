<?php

declare(strict_types=1);

namespace Wio\WioPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Wio\WioPayments\DTOs\PaymentResponse charge(string $currency, int $amountInCents, array $metadata = [])
 * @method static \Wio\WioPayments\DTOs\PaymentResponse createPaymentIntent(string $currency, int $amountInCents, array $options = [])
 * @method static \Wio\WioPayments\DTOs\PaymentResponse getPayment(string $paymentId)
 * @method static \Wio\WioPayments\DTOs\PaymentResponse refund(string $paymentId, ?int $amountInCents = null, array $metadata = [])
 * @method static array getBalance()
 *
 * @see \Wio\WioPayments\WioPayments
 */
class WioPayments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Wio\WioPayments\WioPayments::class;
    }
}