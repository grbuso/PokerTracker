<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthReport
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $queuedJobs = (int) DB::table('jobs')->count();
        $stuckJobs = (int) DB::table('jobs')
            ->where('available_at', '<', now()->subMinutes(5)->getTimestamp())
            ->count();
        $recentFailures = (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subMinutes(10))
            ->count();

        $lastSeenRaw = Cache::get('worker:last_seen');
        $lastSeen = is_string($lastSeenRaw) && $lastSeenRaw !== ''
            ? Carbon::parse($lastSeenRaw)
            : null;
        $workerStale = $lastSeen === null || $lastSeen->lt(now()->subMinutes(10));
        $workerProblem = $workerStale && ($queuedJobs > 0 || $recentFailures > 0);

        $degraded = ! $this->databaseIsAvailable()
            || $stuckJobs > 0
            || $recentFailures > 0
            || $workerProblem
            || ! $this->roboflowIsConfigured();

        return [
            'status' => $degraded ? 'degraded' : 'ok',
            'db' => $this->databaseIsAvailable() ? 'ok' : 'fail',
            'queued_jobs' => $queuedJobs,
            'stuck_jobs' => $stuckJobs,
            'recent_failed_jobs' => $recentFailures,
            'worker_last_seen' => $lastSeen?->toIso8601String(),
            'worker_stale' => $workerStale,
            'worker_problem' => $workerProblem,
            'roboflow_configured' => $this->roboflowIsConfigured(),
            'discord_configured' => filled(config('services.discord.bot_token')),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function roboflowIsConfigured(): bool
    {
        return filled(config('services.roboflow.key'))
            && filled(config('services.roboflow.model'));
    }
}
