<?php

namespace App\Http\Controllers;

use App\Actions\CreateRound;
use App\Http\Requests\StoreRoundRequest;
use App\Models\ChipSnapshot;
use App\Models\Round;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoundController extends Controller
{
    // Mostra o formulário
    public function create() 
    {
        return Inertia::render('rounds/Create', [
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // Recebe o formulário e salva
    public function store(StoreRoundRequest $request, CreateRound $action)
    {
        $d = $request->validated();
        $round = $action->handle($d['total_chips'], $d['chips_per_player'], $d['user_ids']);

        return redirect("/rounds/{$round->id}");
    }

        public function show(Round $round)
    {
        return Inertia::render('rounds/Show', [
            'round' => $round->load('players'),
            'snapshots' => $round->snapshots()
                ->orderBy('recorded_on')
                ->get(['user_id', 'recorded_on', 'chips']),
        ]);
    }

    public function storeSnapshot(Request $request, Round $round)
    {
        $data = $request->validate([
            'recorded_on' => ['required', 'date'],
            'chips'       => ['required', 'array'],
            'chips.*'     => ['required', 'integer', 'min:0'],
        ]);

        // só aceita jogadores da rodada que ainda não foram eliminados
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
                        'round_id'    => $round->id,
                        'user_id'     => $userId,
                        'recorded_on' => $data['recorded_on'],
                    ],
                    ['chips' => $chips],
                );

                $round->players()->updateExistingPivot($userId, [
                    'chips'         => $chips,
                    'eliminated_at' => $chips === 0 ? $data['recorded_on'] : null,
                ]);
            }
        });

        return redirect("/rounds/{$round->id}");
    }
}