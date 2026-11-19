<?php

namespace App\Console\Commands;

use App\Actions\SendAdminAlert;
use App\Support\HealthReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckPokerTrackerHealth extends Command
{
    protected $signature = 'pokertracker:health-check';

    protected $description = 'Verifica fila, worker e integrações e alerta os admins no Discord quando algo está degradado';

    public function handle(HealthReport $report, SendAdminAlert $alerts): int
    {
        $health = $report->toArray();

        if ($health['status'] === 'ok') {
            Cache::forget('alert:health:degraded');

            return self::SUCCESS;
        }

        if (Cache::has('alert:health:degraded')) {
            return self::SUCCESS;
        }

        $alerts->handle(
            'PokerTracker degradado',
            'O health check encontrou um problema. Detalhes abaixo.',
            [
                'status' => $health['status'],
                'db' => $health['db'],
                'queued_jobs' => $health['queued_jobs'],
                'stuck_jobs' => $health['stuck_jobs'],
                'recent_failed_jobs' => $health['recent_failed_jobs'],
                'worker_last_seen' => $health['worker_last_seen'] ?? 'nunca',
                'roboflow_configured' => $health['roboflow_configured'] ? 'sim' : 'não',
                'discord_configured' => $health['discord_configured'] ? 'sim' : 'não',
            ],
        );

        Cache::put('alert:health:degraded', true, now()->addMinutes(30));

        return self::SUCCESS;
    }
}
