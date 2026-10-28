<?php

use App\Models\ChipSnapshot;
use App\Models\Round;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('round show page loads the requested round players', function () {
    $user = User::factory()->create();
    $player = User::factory()->create(['name' => 'Ana']);
    $round = Round::create([
        'starts_on' => '2026-10-28',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 500]);

    $this->actingAs($user);

    $this->get(route('rounds.show', $round))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rounds/Show')
            ->where('round.id', $round->id)
            ->where('round.players.0.name', 'Ana')
        );
});

test('a daily snapshot updates chips and eliminates players at zero', function () {
    $user = User::factory()->create();
    $player = User::factory()->create();
    $round = Round::create([
        'starts_on' => '2026-10-28',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 500]);

    $this->actingAs($user);

    $this->post(route('rounds.snapshots.store', $round), [
        'recorded_on' => '2026-10-29',
        'chips' => [$player->id => 0],
    ])->assertRedirect();

    $snapshot = ChipSnapshot::query()->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->chips)->toBe(0)
        ->and($snapshot->user_id)->toBe($player->id);

    expect($round->players()->first()->pivot->eliminated_at)->not->toBeNull();
});

test('a snapshot ignores players eliminated from the round', function () {
    $user = User::factory()->create();
    $player = User::factory()->create();
    $round = Round::create([
        'starts_on' => '2026-10-28',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, [
        'chips' => 0,
        'eliminated_at' => now(),
    ]);

    $this->actingAs($user);

    $this->post(route('rounds.snapshots.store', $round), [
        'recorded_on' => '2026-10-29',
        'chips' => [$player->id => 100],
    ])->assertRedirect();

    expect(ChipSnapshot::query()->count())->toBe(0);
});

test('creating a round closes the previous active round', function () {
    $user = User::factory()->create();
    $firstPlayer = User::factory()->create();
    $secondPlayer = User::factory()->create();
    $activeRound = Round::create([
        'starts_on' => '2026-10-21',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $activeRound->players()->attach($firstPlayer->id, ['chips' => 500]);

    $this->actingAs($user);

    $this->post(route('rounds.store'), [
        'total_chips' => 1000,
        'chips_per_player' => 500,
        'user_ids' => [$firstPlayer->id, $secondPlayer->id],
    ])->assertRedirect(route('dashboard'));

    expect($activeRound->fresh()->status)->toBe('encerrada');

    $newRound = Round::query()->where('status', 'ativa')->first();

    expect($newRound)->not->toBeNull()
        ->and($newRound->id)->not->toBe($activeRound->id)
        ->and($newRound->players()->count())->toBe(2)
        ->and($newRound->players()->first()->pivot->chips)->toBe(500);
});
