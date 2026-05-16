<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'             => fake()->words(3, true),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'price'            => fake()->randomFloat(2, 50000, 300000),
            'professional_id'  => fake()->numberBetween(1, 10),
            'non_refundable'   => false,
        ];
    }

    public function nonRefundable(): static
    {
        return $this->state(['non_refundable' => true]);
    }
}
