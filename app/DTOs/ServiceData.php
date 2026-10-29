<?php

namespace App\DTOs;

readonly class ServiceData
{
    public function __construct(
        public string $name,
        public float $cost,
        public ?string $code = null,
        public ?string $description = null,
        public bool $is_active = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            cost: (float) $data['cost'],
            code: ! empty($data['code']) ? strtoupper(trim($data['code'])) : null,
            description: $data['description'] ?? null,
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'cost' => $this->cost,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];
    }
}
