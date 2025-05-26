<?php

declare(strict_types=1);

namespace Wio\WioPayments\DTOs;

readonly class PaymentResponse
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $currency = null,
        public ?int $amount = null,
        public ?string $orderId = null,
        public ?array $metadata = null,
        public ?string $clientSecret = null,
        public ?\DateTimeImmutable $createdAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: $data['status'],
            currency: $data['currency'] ?? null,
            amount: $data['amount'] ?? null,
            orderId: $data['order_id'] ?? null,
            metadata: $data['metadata'] ?? null,
            clientSecret: $data['client_secret'] ?? null,
            createdAt: isset($data['created_at']) 
                ? new \DateTimeImmutable($data['created_at']) 
                : null
        );
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['succeeded', 'completed']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'canceled']);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'order_id' => $this->orderId,
            'metadata' => $this->metadata,
            'client_secret' => $this->clientSecret,
            'created_at' => $this->createdAt?->format('c')
        ];
    }
}