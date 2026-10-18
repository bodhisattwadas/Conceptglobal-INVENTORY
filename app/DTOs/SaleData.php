<?php

namespace App\DTOs;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use Carbon\Carbon;

readonly class SaleData
{
    /**
     * @param  SaleItemData[]  $items
     */
    public function __construct(
        public Carbon $sale_date,
        public PaymentMethod $payment_method,
        public int $created_by,
        public array $items,
        public ?int $customer_id = null,
        public SaleStatus $status = SaleStatus::COMPLETED,
        public ?string $notes = null,
        public float $cash_received = 0,
        public float $change = 0,
        public float $global_discount = 0,
        public ?int $coupon_id = null,
        public ?string $coupon_code = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sale_date: Carbon::parse($data['sale_date']),
            payment_method: PaymentMethod::from($data['payment_method']),
            created_by: (int) $data['created_by'],
            items: array_map(fn ($item) => SaleItemData::fromArray($item), $data['items']),
            customer_id: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            status: isset($data['status']) ? SaleStatus::from($data['status']) : SaleStatus::COMPLETED,
            notes: $data['notes'] ?? null,
            cash_received: (float) ($data['cash_received'] ?? 0),
            change: (float) ($data['change'] ?? 0),
            global_discount: (float) ($data['global_discount'] ?? 0),
            coupon_id: isset($data['coupon_id']) && ! empty($data['coupon_id']) ? (int) $data['coupon_id'] : null,
            coupon_code: ! empty($data['coupon_code']) ? strtoupper(trim($data['coupon_code'])) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'sale_date' => $this->sale_date->toDateTimeString(),
            'payment_method' => $this->payment_method->value,
            'created_by' => $this->created_by,
            'items' => array_map(fn ($item) => $item->toArray(), $this->items),
            'customer_id' => $this->customer_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'cash_received' => $this->cash_received,
            'change' => $this->change,
            'global_discount' => $this->global_discount,
            'coupon_id' => $this->coupon_id,
            'coupon_code' => $this->coupon_code,
        ];
    }
}
