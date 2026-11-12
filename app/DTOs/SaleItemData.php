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
        public ?string $name = null,
        public ?string $barcode = null,
        public bool $is_manual = false,
        public ?int $matched_product_id = null,
        public ?int $id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $isManual = (bool) ($data['is_manual'] ?? false);
        $productId = ! empty($data['product_id']) ? (int) $data['product_id'] : null;
        $matchedProductId = ! empty($data['matched_product_id']) ? (int) $data['matched_product_id'] : ($isManual ? $productId : null);

        // If explicitly manual, clear direct product_id so it flows into reconciliation
        if ($isManual) {
            $productId = null;
        }

        return new self(
            product_id: $productId,
            quantity: (int) $data['quantity'],
            unit_price: (float) $data['unit_price'],
            discount: (float) ($data['discount'] ?? 0),
            service_id: ! empty($data['service_id']) ? (int) $data['service_id'] : null,
            name: ! empty($data['name']) ? trim((string) $data['name']) : null,
            barcode: ! empty($data['barcode']) ? trim((string) $data['barcode']) : null,
            is_manual: $isManual,
            matched_product_id: $matchedProductId,
            id: ! empty($data['id']) ? (int) $data['id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'service_id' => $this->service_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'is_manual' => $this->is_manual,
            'matched_product_id' => $this->matched_product_id,
        ];
    }

    public function isService(): bool
    {
        return ! empty($this->service_id);
    }

    public function isManual(): bool
    {
        return $this->is_manual || (empty($this->product_id) && empty($this->service_id));
    }
}
