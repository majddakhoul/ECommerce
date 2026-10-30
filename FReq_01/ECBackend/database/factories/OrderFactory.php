<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_price' => fake()->randomFloat(2, 10, 500),
            'status' => OrderStatus::PENDING,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
