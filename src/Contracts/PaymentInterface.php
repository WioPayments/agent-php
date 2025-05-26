<?php

declare(strict_types=1);

namespace Wio\WioPayments\Contracts;

use Wio\WioPayments\DTOs\PaymentResponse;

interface PaymentInterface
{
    public function charge(string $currency, int $amountInCents, array $metadata = []): PaymentResponse;
    
    public function createPaymentIntent(string $currency, int $amountInCents, array $options = []): PaymentResponse;
    
    public function getPayment(string $paymentId): PaymentResponse;
    
    public function refund(string $paymentId, ?int $amountInCents = null, array $metadata = []): PaymentResponse;
    
    public function getBalance(): array;
}