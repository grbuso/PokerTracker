<?php

namespace App\Http\Controllers;

use App\Actions\DetermineRoundWinner;
use App\Models\ChipSnapshot;
use App\Models\Round;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PlayerProfileController extends Controller
{
    /**
     * Display the player profile with stats and round history.
     */
    public function show(User $player, DetermineRoundWinner $determineWinner): Response
    {
        $rounds = $player->rounds()
            ->with(['players' => fn ($query) => $query->orderByDesc('round_user.chips')])
            ->orderByDesc('rounds.id')
            ->get();

        $closedRounds = $rounds
            ->where('status', 'encerrada')
            ->sortByDesc('id')
            ->values();

        $winners = $closedRounds->mapWithKeys(
            fn (Round $round): array => [$round->id => $determineWinner->handle($round)?->id],
        );

        $wins = $winners
            ->filter(fn (?int $userId): bool => $userId === $player->id)
            ->count();

        $streak = 0;

        foreach ($closedRounds as $round) {
            if (($winners[$round->id] ?? null) !== $player->id) {
                break;
            }

            $streak++;
        }

        $bestStack = ChipSnapshot::query()
            ->where('user_id', $player->id)
            ->max('chips');

        return Inertia::render('players/Show', [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'avatar' => $player->avatar,
                'is_admin' => $player->is_admin,
            ],
            'stats' => [
                'wins' => $wins,
                'rounds_played' => $closedRounds->count(),
                'win_rate' => $closedRounds->isEmpty()
                    ? null
                    : ($wins / $closedRounds->count()) * 100,
                'streak' => $streak,
                'best_stack' => $bestStack !== null ? (int) $bestStack : null,
            ],
            'rounds' => $rounds->map(fn (Round $round): array => [
                'id' => $round->id,
                'starts_on' => $round->starts_on->format('d/m/Y'),
                'status' => $round->status,
                'players_count' => $round->players->count(),
                'chips' => (int) $round->pivot->chips,
                'eliminated_at' => $round->pivot->eliminated_at?->format('d/m/Y'),
                'won' => ($winners[$round->id] ?? null) === $player->id,
            ])->values(),
        ]);
    }
}
