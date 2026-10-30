<?php

namespace App\Http\Controllers;

use App\Models\ChipSnapshot;
use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $rounds = Round::query()
            ->with(['players' => fn ($query) => $query->orderByDesc('round_user.chips')])
            ->latest('id')
            ->get();

        $activeRound = $rounds->firstWhere('status', 'ativa');
        $users = User::query()->orderBy('name')->get(['id', 'name']);
        $lastSnapshotOn = ChipSnapshot::query()->max('recorded_on');

        return Inertia::render('Dashboard', [
            'canManage' => $request->user()->can('manage-game'),
            'users' => $users,
            'stats' => [
                'total_players' => $users->count(),
                'total_rounds' => $rounds->count(),
                'closed_rounds' => $rounds->where('status', 'encerrada')->count(),
                'last_snapshot_on' => $lastSnapshotOn ? Carbon::parse($lastSnapshotOn)->format('d/m/Y') : null,
            ],
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
        $lastSnapshotOn = $round->snapshots()->max('recorded_on');
        $activePlayers = $round->players
            ->filter(fn (User $user) => $user->pivot->eliminated_at === null);
        $leaderChips = $activePlayers->max(fn (User $user) => (int) $user->pivot->chips);
        $leaders = $leaderChips === null || $leaderChips <= 0
            ? collect()
            : $activePlayers->filter(fn (User $user) => (int) $user->pivot->chips === $leaderChips);

        return [
            'id' => $round->id,
            'starts_on' => $round->starts_on->format('d/m/Y'),
            'total_chips' => $round->total_chips,
            'players_count' => $round->players->count(),
            'active_players_count' => $activePlayers->count(),
            'leader' => $leaders->isEmpty() ? null : [
                'name' => $leaders->count() === 1 ? $leaders->first()->name : 'Empate',
                'chips' => (int) $leaderChips,
                'tied' => $leaders->count() > 1,
                'count' => $leaders->count(),
            ],
            'last_snapshot_on' => $lastSnapshotOn ? Carbon::parse($lastSnapshotOn)->format('d/m/Y') : null,
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
