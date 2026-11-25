<?php

namespace App\DTOs;

use App\Http\Requests\RegisterRequest;

class RegisterDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: strtolower($request->validated('email')),
            password: $request->validated('password')
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
