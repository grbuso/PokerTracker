<?php

namespace App\Actions;

use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DetermineRoundWinner
{
    public function handle(Round $round): ?User
    {
        $topPlayers = $this->topPlayers($round);

        return $topPlayers?->count() === 1 ? $topPlayers->first() : null;
    }

    public function isTie(Round $round): bool
    {
        return ($this->topPlayers($round)?->count() ?? 0) > 1;
    }

    /**
     * @return Collection<int, User>|null
     */
    private function topPlayers(Round $round): ?Collection
    {
        if ($round->status !== 'encerrada' || $round->players->isEmpty()) {
            return null;
        }

        $survivors = $round->players->filter(
            fn (User $user) => $user->pivot->eliminated_at === null,
        );

        $candidates = $survivors->isNotEmpty() ? $survivors : $round->players;
        $topChips = $candidates->max(fn (User $user) => (int) $user->pivot->chips);

        if ($topChips === null || $topChips <= 0) {
            return null;
        }

        return $candidates
            ->filter(fn (User $user) => (int) $user->pivot->chips === $topChips)
            ->values();
    }
}
