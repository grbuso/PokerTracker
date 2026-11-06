<?php

namespace Database\Factories;

use App\Models\ChipSubmission;
use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChipSubmission>
 */
class ChipSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'user_id' => User::factory(),
            'recorded_on' => now()->toDateString(),
            'chips' => 1000,
            'chip_counts' => null,
            'source' => 'discord',
            'submitted_at' => now(),
            'registered_at' => null,
        ];
    }
}
