<?php

use App\Actions\SendAdminAlert;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('admin alerts are sent only to administrators via discord dm', function () {
    config(['services.discord.bot_token' => 'bot-token']);

    $admin = User::factory()->admin()->create(['discord_id' => 'admin-discord']);
    $player = User::factory()->create(['discord_id' => 'player-discord']);

    Http::fake([
        'discord.com/api/v10/users/@me/channels' => Http::response(['id' => 'admin-dm-channel'], 200),
        'discord.com/api/v10/channels/admin-dm-channel/messages' => Http::response(['id' => 'message-1'], 200),
    ]);

    app(SendAdminAlert::class)->handle(
        'Teste',
        'Mensagem de teste',
        ['correlation_id' => 'abc-123'],
    );

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://discord.com/api/v10/users/@me/channels'
        && $request['recipient_id'] === 'admin-discord'
        && $request->hasHeader('Authorization', 'Bot bot-token'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://discord.com/api/v10/channels/admin-dm-channel/messages'
        && str_contains($request['content'], 'Mensagem de teste')
        && str_contains($request['content'], 'abc-123'));

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'users/@me/channels')
        && ($request['recipient_id'] ?? null) === 'player-discord');
});

test('admin alerts are skipped when the discord bot token is missing', function () {
    config(['services.discord.bot_token' => '']);

    User::factory()->admin()->create(['discord_id' => 'admin-discord']);

    Http::fake();

    app(SendAdminAlert::class)->handle('Teste', 'Mensagem de teste');

    Http::assertNothingSent();
});
