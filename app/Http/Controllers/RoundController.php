<?php

namespace App\Http\Controllers;

use App\Actions\CreateRound;
use App\Http\Requests\StoreRoundRequest;
use App\Models\ChipSnapshot;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoundController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    public function store(StoreRoundRequest $request, CreateRound $action): RedirectResponse
    {
        $data = $request->validated();

        $action->handle($data['total_chips'], $data['chips_per_player'], $data['user_ids']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Rodada criada com sucesso.'),
        ]);

        return redirect()->route('dashboard');
    }

    public function show(Request $request, Round $round): Response
    {
        return Inertia::render('rounds/Show', [
            'canManage' => $request->user()->can('manage-game'),
            'round' => $round->load([
                'players' => fn ($query) => $query->orderBy('name'),
            ]),
            'snapshots' => $round->snapshots()
                ->orderBy('recorded_on')
                ->get(['user_id', 'recorded_on', 'chips']),
        ]);
    }

    public function storeSnapshot(Request $request, Round $round): RedirectResponse
    {
        if ($round->status !== 'ativa') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Rodada encerrada não pode ser alterada.'),
            ]);

            return back();
        }

        $data = $request->validate([
            'recorded_on' => ['required', 'date'],
            'chips' => ['required', 'array'],
            'chips.*' => ['required', 'integer', 'min:0'],
        ]);

        $activeIds = $round->players()
            ->wherePivotNull('eliminated_at')
            ->pluck('users.id')
            ->all();

        DB::transaction(function () use ($data, $round, $activeIds) {
            foreach ($data['chips'] as $userId => $chips) {
                if (! in_array((int) $userId, $activeIds)) {
                    continue;
                }

                ChipSnapshot::updateOrCreate(
                    [
                        'round_id' => $round->id,
                        'user_id' => $userId,
                        'recorded_on' => $data['recorded_on'],
                    ],
                    ['chips' => $chips],
                );

                $round->players()->updateExistingPivot($userId, [
                    'chips' => $chips,
                    'eliminated_at' => $chips === 0 ? $data['recorded_on'] : null,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Dia registrado com sucesso.'),
        ]);

        return back();
    }

    public function close(Round $round): RedirectResponse
    {
        if ($round->status !== 'ativa') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Rodada já está encerrada.'),
            ]);

            return back();
        }

        $round->update(['status' => 'encerrada']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Rodada encerrada com sucesso.'),
        ]);

        return back();
    }

    public function destroy(Round $round): RedirectResponse
    {
        $round->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Rodada removida com sucesso.'),
        ]);

        return redirect()->route('dashboard');
    }
}
