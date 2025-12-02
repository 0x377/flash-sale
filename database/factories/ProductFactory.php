<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'initial_stock' => $this->faker->numberBetween(100, 1000),
            'available_stock' => function (array $attributes) {
                return $attributes['initial_stock'];
            },
            'is_active' => true,
            'metadata' => [
                'sku' => $this->faker->unique()->ean13(),
                'category' => $this->faker->word(),
                'tags' => $this->faker->words(3),
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function lowStock(int $threshold = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'available_stock' => $this->faker->numberBetween(1, $threshold),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_stock' => 0,
        ]);
    }
}
