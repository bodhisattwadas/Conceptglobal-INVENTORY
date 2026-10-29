<?php

namespace App\DTOs;

readonly class SaleItemData
{
    public function __construct(
        public ?int $product_id,
        public int $quantity,
        public float $unit_price,
        public float $discount = 0,
        public ?int $service_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            product_id: ! empty($data['product_id']) ? (int) $data['product_id'] : null,
            quantity: (int) $data['quantity'],
            unit_price: (float) $data['unit_price'],
            discount: (float) ($data['discount'] ?? 0),
            service_id: ! empty($data['service_id']) ? (int) $data['service_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->product_id,
            'service_id' => $this->service_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
        ];
    }

    public function isService(): bool
    {
        return ! empty($this->service_id);
    }
}
