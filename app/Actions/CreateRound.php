<?php

namespace App\Actions;

use App\Models\Round;
use Illuminate\Support\Facades\DB;

class CreateRound
{
    public function handle(int $totalChips, int $chipsPerPlayer, array $userIds): Round
    {
        return DB::transaction(function () use ($totalChips, $chipsPerPlayer, $userIds) {
            Round::where('status', 'ativa')->update(['status' => 'encerrada']);

            $round = round::create([
                'starts_on'     => now(),
                'total_chips'   => $totalChips,
                'status'        => 'ativa',
            ]);

            $round->players()->attach(
                collect($userIds)->mapWithKeys(fn ($id) => [$id => ['chips' => $chipsPerPlayer]])->all()
            );

            return $round;
        });
    }
}
