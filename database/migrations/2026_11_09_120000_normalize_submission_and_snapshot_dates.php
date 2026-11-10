<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza datas antigas (ISO/datetime) para Y-m-d e remove duplicatas
     * que possam ter sido criadas antes da correção.
     */
    public function up(): void
    {
        $this->normalizeTable('chip_submissions', withRegistration: true);
        $this->normalizeTable('chip_snapshots');

        // O bug antigo criava o snapshot, mas não marcava os envios como
        // registrados. Se já existe snapshot do jogador no dia, o dia foi
        // registrado e o envio deve sair da lista de pendentes.
        DB::table('chip_submissions')
            ->whereNull('registered_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('chip_snapshots')
                    ->whereColumn('chip_snapshots.round_id', 'chip_submissions.round_id')
                    ->whereColumn('chip_snapshots.user_id', 'chip_submissions.user_id')
                    ->whereColumn('chip_snapshots.recorded_on', 'chip_submissions.recorded_on');
            })
            ->update(['registered_at' => now()]);
    }

    public function down(): void
    {
        //
    }

    private function normalizeTable(string $table, bool $withRegistration = false): void
    {
        $columns = ['id', 'round_id', 'user_id', 'recorded_on', 'updated_at'];

        if ($withRegistration) {
            $columns[] = 'registered_at';
        }

        $groups = DB::table($table)
            ->get($columns)
            ->groupBy(fn (object $row): string => implode('|', [
                $row->round_id,
                $row->user_id,
                substr((string) $row->recorded_on, 0, 10),
            ]));

        foreach ($groups as $group) {
            $kept = $group->sortBy([
                ['updated_at', 'desc'],
                ['id', 'desc'],
            ])->first();

            $registeredAt = $withRegistration
                ? $group->pluck('registered_at')->filter()->max()
                : null;

            foreach ($group as $row) {
                if ($row->id !== $kept->id) {
                    DB::table($table)->where('id', $row->id)->delete();
                }
            }

            $values = [
                'recorded_on' => substr((string) $kept->recorded_on, 0, 10),
            ];

            if ($withRegistration) {
                $values['registered_at'] = $registeredAt;
            }

            DB::table($table)->where('id', $kept->id)->update($values);
        }
    }
};
