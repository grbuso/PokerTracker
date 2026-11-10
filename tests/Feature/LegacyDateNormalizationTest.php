<?php

use App\Models\ChipSubmission;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('legacy date values are normalized and already registered days are cleared', function () {
    $player = User::factory()->create();
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    // Simula os valores gravados pelo bug antigo (ISO/datetime).
    DB::table('chip_snapshots')->insert([
        'round_id' => $round->id,
        'user_id' => $player->id,
        'recorded_on' => '2026-11-06T00:00:00.000000Z',
        'chips' => 900,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $submission = ChipSubmission::factory()->create([
        'round_id' => $round->id,
        'user_id' => $player->id,
        'recorded_on' => '2026-11-06',
        'chips' => 900,
        'registered_at' => null,
    ]);

    DB::table('chip_submissions')->where('id', $submission->id)->update([
        'recorded_on' => '2026-11-06 00:00:00',
    ]);

    $migration = require database_path('migrations/2026_11_09_120000_normalize_submission_and_snapshot_dates.php');
    $migration->up();

    expect(DB::table('chip_snapshots')->value('recorded_on'))->toBe('2026-11-06')
        ->and(DB::table('chip_submissions')->value('recorded_on'))->toBe('2026-11-06')
        ->and($submission->fresh()->registered_at)->not->toBeNull();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('rounds.submissions.index', $round))
        ->assertInertia(fn (Assert $page) => $page->where('dates', []));
});
