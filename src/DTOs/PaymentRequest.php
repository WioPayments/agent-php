<?php

declare(strict_types=1);

namespace Wio\WioPayments\DTOs;

readonly class PaymentRequest
{
    public function __construct(
        public string $currency,
        public int $amount,
        public array $metadata = [],
        public ?string $orderId = null,
        public ?array $customerData = null
    ) {}

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'amount' => $this->amount,
            'metadata' => $this->metadata,
            'order_id' => $this->orderId ?? uniqid('wio_'),
            'customer_data' => $this->customerData,
        ];
    }
}