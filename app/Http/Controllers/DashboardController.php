<?php

namespace App\Http\Controllers;

use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $rounds = Round::query()
            ->with(['players' => fn ($query) => $query->orderByDesc('round_user.chips')])
            ->latest('id')
            ->get();

        $activeRound = $rounds->firstWhere('status', 'ativa');

        return Inertia::render('Dashboard', [
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'activeRound' => $activeRound ? $this->activeRoundData($activeRound) : null,
            'rounds' => $rounds->map(fn (Round $round) => [
                'id' => $round->id,
                'starts_on' => $round->starts_on->format('d/m/Y'),
                'total_chips' => $round->total_chips,
                'status' => $round->status,
                'players_count' => $round->players->count(),
                'winner' => $this->winnerFor($round)?->name,
            ])->values(),
            'ranking' => $this->ranking(
                $rounds->where('status', 'encerrada'),
                $activeRound,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function activeRoundData(Round $round): array
    {
        return [
            'id' => $round->id,
            'starts_on' => $round->starts_on->format('d/m/Y'),
            'total_chips' => $round->total_chips,
            'players' => $round->players
                ->sortBy('chips')
                ->values()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'chips' => (int) $user->pivot->chips,
                    'eliminated_at' => $user->pivot->eliminated_at,
                ]),
            'snapshots' => $round->snapshots()
                ->orderBy('recorded_on')
                ->get(['user_id', 'recorded_on', 'chips']),
        ];
    }

    /**
     * @param  Collection<int, Round>  $closedRounds
     * @return array<int, array<string, mixed>>
     */
    private function ranking(Collection $closedRounds, ?Round $activeRound): array
    {
        $wins = [];

        foreach ($closedRounds as $round) {
            $winner = $this->winnerFor($round);

            if ($winner === null) {
                continue;
            }

            $wins[$winner->id] = ($wins[$winner->id] ?? 0) + 1;
        }

        $activeChips = $activeRound
            ? $activeRound->players->mapWithKeys(
                fn (User $user) => [$user->id => (int) $user->pivot->chips],
            )
            : collect();

        return User::query()
            ->withCount('rounds')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'wins' => $wins[$user->id] ?? 0,
                'rounds_played' => $user->rounds_count,
                'current_chips' => $activeChips->get($user->id),
            ])
            ->sortByDesc('wins')
            ->values()
            ->all();
    }

    private function winnerFor(Round $round): ?User
    {
        if ($round->status !== 'encerrada' || $round->players->isEmpty()) {
            return null;
        }

        $survivors = $round->players->filter(
            fn (User $user) => $user->pivot->eliminated_at === null,
        );

        $candidates = $survivors->isNotEmpty() ? $survivors : $round->players;
        $topChips = $candidates->max(fn (User $user) => (int) $user->pivot->chips);
        $topPlayers = $candidates->filter(
            fn (User $user) => (int) $user->pivot->chips === $topChips,
        );

        if ($topChips === null || $topChips <= 0 || $topPlayers->count() !== 1) {
            return null;
        }

        return $topPlayers->first();
    }
}
