<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.discord.application_id' => 'app-1',
        'services.discord.bot_token' => 'bot-token',
        'services.discord.guild_id' => 'guild-1',
    ]);
});

test('registers guild commands using the bot authorization scheme', function () {
    Http::fake([
        'discord.com/api/v10/applications/app-1/guilds/guild-1/commands' => Http::response([], 200),
    ]);

    $this->artisan('discord:register-commands')->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'PUT'
            && $request->url() === 'https://discord.com/api/v10/applications/app-1/guilds/guild-1/commands'
            && $request->hasHeader('Authorization', 'Bot bot-token')
            && collect($request->data())->pluck('name')->all() === ['fichas', 'vincular'];
    });
});

test('registers global commands when no guild is configured', function () {
    config(['services.discord.guild_id' => null]);

    Http::fake([
        'discord.com/api/v10/applications/app-1/commands' => Http::response([], 200),
    ]);

    $this->artisan('discord:register-commands')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://discord.com/api/v10/applications/app-1/commands'
        && $request->hasHeader('Authorization', 'Bot bot-token'));
});

test('fails without application id or bot token', function () {
    config([
        'services.discord.application_id' => null,
        'services.discord.bot_token' => null,
    ]);

    Http::fake();

    $this->artisan('discord:register-commands')
        ->expectsOutput('Configure DISCORD_APPLICATION_ID and DISCORD_BOT_TOKEN first.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

test('explains an unauthorized response', function () {
    Http::fake([
        'discord.com/api/v10/applications/app-1/guilds/guild-1/commands' => Http::response([
            'message' => '401: Unauthorized',
            'code' => 0,
        ], 401),
    ]);

    $this->artisan('discord:register-commands')
        ->expectsOutputToContain('401 Unauthorized')
        ->assertExitCode(1);
});
