<?php

use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('only administrators can manage the game', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    expect(Gate::forUser($admin)->allows('manage-game'))->toBeTrue()
        ->and(Gate::forUser($player)->allows('manage-game'))->toBeFalse();
});

test('administrators can see the player management page', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin']);
    $player = User::factory()->create(['name' => 'Ana']);

    $this->actingAs($admin)
        ->get(route('admin.players.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Players')
            ->where('players.0.name', 'Admin')
            ->where('players.0.is_admin', true)
            ->where('players.1.name', 'Ana')
            ->where('players.1.is_current_user', false)
        );
});

test('players cannot see the player management page', function () {
    $player = User::factory()->create();

    $this->actingAs($player)
        ->get(route('admin.players.index'))
        ->assertForbidden();
});

test('administrators can create a player', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.players.store'), [
            'name' => 'Novo Jogador',
            'email' => 'novo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('admin.players.index'));

    $player = User::query()->where('email', 'novo@example.com')->first();

    expect($player)->not->toBeNull()
        ->and($player->name)->toBe('Novo Jogador')
        ->and($player->is_admin)->toBeFalse()
        ->and($player->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $player->password))->toBeTrue();
});

test('players cannot create other players', function () {
    $player = User::factory()->create();

    $this->actingAs($player)
        ->post(route('admin.players.store'), [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'intruso@example.com')->exists())->toBeFalse();
});

test('player data is validated', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.players.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

test('administrators can update a player without changing the password', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create([
        'name' => 'Ana',
        'email' => 'ana@example.com',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.players.update', $player), [
            'name' => 'Ana Silva',
            'email' => 'ana.silva@example.com',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('admin.players.index'));

    $player->refresh();

    expect($player->name)->toBe('Ana Silva')
        ->and($player->email)->toBe('ana.silva@example.com')
        ->and(Hash::check('password', $player->password))->toBeTrue();
});

test('administrators can change a player password', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'email' => $player->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect(route('admin.players.index'));

    expect(Hash::check('new-password', $player->fresh()->password))->toBeTrue();
});

test('players cannot update other players', function () {
    $player = User::factory()->create();
    $otherPlayer = User::factory()->create(['name' => 'Ana']);

    $this->actingAs($player)
        ->put(route('admin.players.update', $otherPlayer), [
            'name' => 'Alterado',
            'email' => 'alterado@example.com',
        ])
        ->assertForbidden();

    expect($otherPlayer->fresh()->name)->toBe('Ana');
});

test('administrators can delete a player without round history', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.players.destroy', $player))
        ->assertRedirect(route('admin.players.index'));

    expect(User::query()->whereKey($player->id)->exists())->toBeFalse();
});

test('administrators cannot delete a player with round history', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $round = Round::create([
        'starts_on' => '2026-10-28',
        'total_chips' => 1000,
        'status' => 'encerrada',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    $this->actingAs($admin)
        ->from(route('admin.players.index'))
        ->delete(route('admin.players.destroy', $player))
        ->assertRedirect(route('admin.players.index'));

    expect(User::query()->whereKey($player->id)->exists())->toBeTrue();
});

test('administrators cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.players.destroy', $admin))
        ->assertForbidden();

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('players cannot delete other players', function () {
    $player = User::factory()->create();
    $otherPlayer = User::factory()->create();

    $this->actingAs($player)
        ->delete(route('admin.players.destroy', $otherPlayer))
        ->assertForbidden();

    expect(User::query()->whereKey($otherPlayer->id)->exists())->toBeTrue();
});
