<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 5, 500);

        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'price' => $price,
            'cost' => round($price * 0.6, 2),
            'stock' => fake()->numberBetween(0, 100),
            'category' => fake()->randomElement(['electronics', 'clothing', 'books', 'home']),
            'image' => null,
        ];
    }
}
