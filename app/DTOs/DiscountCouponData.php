<?php

namespace App\DTOs;

use App\Enums\CouponType;

class DiscountCouponData
{
    public function __construct(
        public readonly string $code,
        public readonly CouponType $type,
        public readonly int $value,
        public readonly ?int $min_purchase_amount,
        public readonly ?int $max_discount_amount,
        public readonly ?int $usage_limit,
        public readonly ?string $starts_at,
        public readonly ?string $expires_at,
        public readonly bool $is_active,
        public readonly ?string $description,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: strtoupper($data['code']),
            type: $data['type'] instanceof CouponType ? $data['type'] : CouponType::from($data['type']),
            value: (int) $data['value'],
            min_purchase_amount: empty($data['min_purchase_amount']) ? null : (int) $data['min_purchase_amount'],
            max_discount_amount: empty($data['max_discount_amount']) ? null : (int) $data['max_discount_amount'],
            usage_limit: empty($data['usage_limit']) ? null : (int) $data['usage_limit'],
            starts_at: $data['starts_at'] ?? null,
            expires_at: $data['expires_at'] ?? null,
            is_active: (bool) ($data['is_active'] ?? true),
            description: empty($data['description']) ? null : $data['description'],
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'min_purchase_amount' => $this->min_purchase_amount,
            'max_discount_amount' => $this->max_discount_amount,
            'usage_limit' => $this->usage_limit,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'is_active' => $this->is_active,
            'description' => $this->description,
        ];
    }
}
