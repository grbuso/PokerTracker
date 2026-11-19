<?php

use App\Models\User;

test('guests cannot access the health report', function () {
    $this->get(route('health'))->assertRedirect(route('login'));
});

test('non administrators cannot access the health report', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('health'))
        ->assertForbidden();
});

test('administrators can access the health report', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('health'))
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'db',
            'queued_jobs',
            'stuck_jobs',
            'recent_failed_jobs',
            'worker_last_seen',
            'worker_stale',
            'worker_problem',
            'roboflow_configured',
            'discord_configured',
            'checked_at',
        ]);
});
