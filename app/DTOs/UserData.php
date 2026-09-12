<?php

namespace App\DTOs;

class UserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $username,
        public readonly string $email,
        public readonly string $role = 'sales',
        public readonly ?string $profile_photo_path = null,
        public readonly ?string $password = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            username: $data['username'],
            email: $data['email'],
            role: $data['role'] ?? 'sales',
            profile_photo_path: $data['profile_photo_path'] ?? null,
            password: $data['password'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'profile_photo_path' => $this->profile_photo_path,
            'password' => $this->password,
        ];
    }
}
