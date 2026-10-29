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
                'tie' => $this->isTie($round),
            ])->values(),
            'ranking' => $this->ranking($rounds),
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
     * @param  Collection<int, Round>  $rounds
     * @return array<int, array<string, mixed>>
     */
    private function ranking(Collection $rounds): array
    {
        $closedRounds = $rounds
            ->where('status', 'encerrada')
            ->sortByDesc('id')
            ->values();

        $wins = [];
        $winners = [];

        foreach ($closedRounds as $round) {
            $winner = $this->winnerFor($round);

            if ($winner === null) {
                continue;
            }

            $winners[$round->id] = $winner->id;
            $wins[$winner->id] = ($wins[$winner->id] ?? 0) + 1;
        }

        $users = User::query()
            ->withCount([
                'rounds' => fn ($query) => $query->where('rounds.status', 'encerrada'),
            ])
            ->orderBy('name')
            ->get();

        $streaks = $users->mapWithKeys(function (User $user) use ($closedRounds, $winners) {
            $streak = 0;

            foreach ($closedRounds as $round) {
                if (($winners[$round->id] ?? null) !== $user->id) {
                    break;
                }

                $streak++;
            }

            return [$user->id => $streak];
        });

        return $users
            ->map(function (User $user) use ($wins, $streaks) {
                $roundsPlayed = $user->rounds_count;
                $winsCount = $wins[$user->id] ?? 0;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'wins' => $winsCount,
                    'rounds_played' => $roundsPlayed,
                    'win_rate' => $roundsPlayed > 0
                        ? ($winsCount / $roundsPlayed) * 100
                        : null,
                    'streak' => $streaks->get($user->id, 0),
                ];
            })
            ->sort(fn (array $a, array $b) => $b['wins'] <=> $a['wins']
                ?: ($b['win_rate'] ?? -1) <=> ($a['win_rate'] ?? -1)
                ?: $a['name'] <=> $b['name'])
            ->values()
            ->all();
    }

    private function winnerFor(Round $round): ?User
    {
        $topPlayers = $this->topPlayers($round);

        return $topPlayers?->count() === 1 ? $topPlayers->first() : null;
    }

    private function isTie(Round $round): bool
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
