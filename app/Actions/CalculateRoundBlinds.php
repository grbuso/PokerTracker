<?php

namespace App\Actions;

use App\Models\Round;

class CalculateRoundBlinds
{
    private const array BIG_BLIND_PERCENTS = [2, 3, 6, 10, 16];

    private const int MIN_BIG_BLIND = 10;

    /**
     * @return array{day: int, small: int, big: int}
     */
    public function handle(Round $round): array
    {
        $day = $this->dayFor($round);
        $initialStack = $round->chips_per_player
            ?? intdiv($round->total_chips, max(1, $round->players->count()));
        $bigBlind = max(
            self::MIN_BIG_BLIND,
            (int) round($initialStack * self::BIG_BLIND_PERCENTS[$day - 1] / 100 / 10) * 10,
        );

        return [
            'day' => $day,
            'small' => intdiv($bigBlind, 2),
            'big' => $bigBlind,
        ];
    }

    private function dayFor(Round $round): int
    {
        $finishedDays = $round->snapshots()->distinct()->count('recorded_on');

        return max(1, min($finishedDays + 1, count(self::BIG_BLIND_PERCENTS)));
    }
}
