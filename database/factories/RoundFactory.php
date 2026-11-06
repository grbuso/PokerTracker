<?php

namespace Database\Factories;

use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starts_on' => now()->toDateString(),
            'total_chips' => 1000,
            'chips_per_player' => 500,
            'status' => 'ativa',
        ];
    }
}
