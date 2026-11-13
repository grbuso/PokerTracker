<?php

namespace App\Actions;

use App\Models\ChipSubmission;
use App\Models\Round;
use App\Models\User;

class StageChipSubmission
{
    /**
     * Creates or updates the pending submission of the day.
     *
     * @param  array<int, int>  $counts
     */
    public function handle(Round $round, User $user, string $date, array $counts, int $total, string $source = 'discord'): ChipSubmission
    {
        $submission = ChipSubmission::query()
            ->where('round_id', $round->id)
            ->where('user_id', $user->id)
            ->whereDate('recorded_on', $date)
            ->latest('id')
            ->first();

        $submission ??= new ChipSubmission([
            'round_id' => $round->id,
            'user_id' => $user->id,
        ]);

        // Mantém sempre a data pura (Y-m-d), mesmo quando a linha existente foi
        // gravada por uma versão antiga com data e hora.
        $submission->recorded_on = $date;

        $submission->fill([
            'chips' => $total,
            'chip_counts' => $counts,
            'source' => $source,
            'submitted_at' => now(),
            'registered_at' => null,
        ]);

        $submission->save();

        return $submission;
    }
}
