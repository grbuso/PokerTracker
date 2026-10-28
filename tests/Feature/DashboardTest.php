<?php

use App\Models\Round;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows the active round players and snapshot history', function () {
    $user = User::factory()->create(['name' => 'Zed']);
    $player = User::factory()->create(['name' => 'Ana']);
    $round = Round::create([
        'starts_on' => '2026-10-28',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 400]);
    $round->snapshots()->create([
        'user_id' => $player->id,
        'recorded_on' => '2026-10-28',
        'chips' => 400,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('activeRound.id', $round->id)
            ->where('activeRound.players.0.name', 'Ana')
            ->where('activeRound.players.0.chips', 400)
            ->where('activeRound.snapshots.0.user_id', $player->id)
            ->where('ranking.0.name', 'Ana')
            ->where('ranking.0.current_chips', 400)
        );
});
