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
            ->where('canManage', false)
            ->where('activeRound.id', $round->id)
            ->where('activeRound.players.0.name', 'Ana')
            ->where('activeRound.players.0.chips', 400)
            ->where('activeRound.snapshots.0.user_id', $player->id)
            ->where('ranking.0.name', 'Ana')
            ->where('ranking.0.win_rate', null)
            ->where('ranking.0.streak', 0)
        );
});

test('ranking counts only finished rounds as played', function () {
    $user = User::factory()->create(['name' => 'Zed']);
    $player = User::factory()->create(['name' => 'Ana']);

    $activeRound = Round::create([
        'starts_on' => '2026-10-28',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $activeRound->players()->attach($player->id, ['chips' => 400]);

    $finishedRound = Round::create([
        'starts_on' => '2026-10-27',
        'total_chips' => 1000,
        'status' => 'encerrada',
    ]);
    $finishedRound->players()->attach($player->id, ['chips' => 500]);
    $finishedRound->players()->attach($user->id, ['chips' => 500]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ranking.0.name', 'Ana')
            ->where('ranking.0.rounds_played', 1)
            ->where('ranking.0.win_rate', 0)
            ->where('ranking.0.streak', 0)
        );
});

test('ranking computes win rate and most recent winning streak', function () {
    $user = User::factory()->create(['name' => 'Zed']);
    $ana = User::factory()->create(['name' => 'Ana']);
    $beto = User::factory()->create(['name' => 'Beto']);
    $caio = User::factory()->create(['name' => 'Caio']);

    $rounds = [
        ['starts_on' => '2026-10-20', 'winners' => ['Caio' => 1500, 'Ana' => 300, 'Beto' => 200]],
        ['starts_on' => '2026-10-21', 'winners' => ['Caio' => 1500, 'Ana' => 300, 'Beto' => 200]],
        ['starts_on' => '2026-10-22', 'winners' => ['Ana' => 1500, 'Beto' => 300]],
        ['starts_on' => '2026-10-23', 'winners' => ['Ana' => 1500, 'Beto' => 300]],
    ];

    $players = ['Ana' => $ana, 'Beto' => $beto, 'Caio' => $caio];

    foreach ($rounds as $roundData) {
        $round = Round::create([
            'starts_on' => $roundData['starts_on'],
            'total_chips' => array_sum($roundData['winners']),
            'status' => 'encerrada',
        ]);

        foreach ($roundData['winners'] as $name => $chips) {
            $round->players()->attach($players[$name]->id, ['chips' => $chips]);
        }
    }

    Round::create([
        'starts_on' => '2026-10-24',
        'total_chips' => 3000,
        'status' => 'ativa',
    ])->players()->attach($ana->id, ['chips' => 3000]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ranking.0.name', 'Caio')
            ->where('ranking.0.wins', 2)
            ->where('ranking.0.win_rate', fn ($value) => abs($value - 100) < 0.0001)
            ->where('ranking.0.streak', 0)
            ->where('ranking.1.name', 'Ana')
            ->where('ranking.1.wins', 2)
            ->where('ranking.1.win_rate', fn ($value) => abs($value - 50) < 0.0001)
            ->where('ranking.1.streak', 2)
            ->where('ranking.2.name', 'Beto')
            ->where('ranking.2.wins', 0)
            ->where('ranking.2.win_rate', 0)
            ->where('ranking.2.streak', 0)
            ->where('ranking.3.name', 'Zed')
            ->where('ranking.3.win_rate', null)
            ->where('ranking.3.streak', 0)
        );
});

test('administrators can manage the game from the dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('canManage', true)
        );
});
