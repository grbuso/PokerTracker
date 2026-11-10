<?php

use App\Models\ChipSnapshot;
use App\Models\ChipSubmission;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));
});

test('administrators can see pending submissions for a round', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create(['name' => 'Ana']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    ChipSubmission::factory()->create([
        'round_id' => $round->id,
        'user_id' => $player->id,
        'recorded_on' => '2026-11-06',
        'chips' => 350,
        'chip_counts' => [100 => 2, 50 => 3, 25 => 0, 10 => 0, 5 => 0],
    ]);

    $this->actingAs($admin)
        ->get(route('rounds.submissions.index', [
            'round' => $round,
            'date' => '2026-11-06',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rounds/Submissions')
            ->where('dates.0', '2026-11-06')
            ->where('selectedDate', '2026-11-06')
            ->where('submissions.0.user_id', $player->id)
            ->where('submissions.0.chips', 350)
            ->where('submissions.0.source', 'discord')
        );
});

test('players cannot see pending submissions', function () {
    $player = User::factory()->create();
    $round = Round::factory()->create();

    $round->players()->attach($player->id, ['chips' => 1000]);

    $this->actingAs($player)
        ->get(route('rounds.submissions.index', $round))
        ->assertForbidden();
});

test('registering a day marks the pending submissions as registered', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    $submission = ChipSubmission::factory()->create([
        'round_id' => $round->id,
        'user_id' => $player->id,
        'recorded_on' => '2026-11-06',
        'chips' => 900,
    ]);

    $this->actingAs($admin)
        ->post(route('rounds.snapshots.store', $round), [
            'recorded_on' => '2026-11-06',
            'chips' => [$player->id => 900],
        ])
        ->assertRedirect();

    expect($submission->fresh()->registered_at)->not->toBeNull()
        ->and(ChipSnapshot::query()->where('user_id', $player->id)->value('chips'))->toBe(900)
        ->and($round->players()->first()->pivot->chips)->toBe(900);

    $this->actingAs($admin)
        ->get(route('rounds.submissions.index', $round))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dates', [])
        );
});

test('registering a day accepts a datetime and still marks submissions as registered', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    $submission = ChipSubmission::factory()->create([
        'round_id' => $round->id,
        'user_id' => $player->id,
        'recorded_on' => '2026-11-06',
        'chips' => 900,
    ]);

    $this->actingAs($admin)
        ->post(route('rounds.snapshots.store', $round), [
            'recorded_on' => '2026-11-06T00:00:00.000000Z',
            'chips' => [$player->id => 900],
        ])
        ->assertRedirect();

    expect($submission->fresh()->registered_at)->not->toBeNull()
        ->and(ChipSnapshot::query()->where('user_id', $player->id)->value('recorded_on'))
        ->toBe('2026-11-06');
});

test('submissions are stored date-only and can be updated on the same day', function () {
    $player = User::factory()->create();
    $round = Round::factory()->create();

    $attributes = [
        'round_id' => $round->id,
        'user_id' => $player->id,
        'recorded_on' => '2026-11-06',
    ];

    ChipSubmission::query()->updateOrCreate($attributes, [
        'chips' => 900,
        'source' => 'discord',
    ]);

    ChipSubmission::query()->updateOrCreate($attributes, [
        'chips' => 950,
        'source' => 'discord',
    ]);

    expect(ChipSubmission::query()->count())->toBe(1)
        ->and(ChipSubmission::query()->first()->chips)->toBe(950)
        ->and(DB::table('chip_submissions')->value('recorded_on'))->toBe('2026-11-06');
});
