<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StockHoldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'product_id' => \App\Models\Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'session_id' => $this->faker->uuid(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(5),
            'status' => 'expired',
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'consumed',
            'consumed_at' => now(),
        ]);
    }

    public function withOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'consumed',
            'consumed_at' => now(),
        ])->afterCreating(function (\App\Models\StockHold $hold) {
            \App\Models\Order::factory()->create(['stock_hold_id' => $hold->id]);
        });
    }
}
