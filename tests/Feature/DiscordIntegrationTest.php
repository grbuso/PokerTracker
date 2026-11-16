<?php

use App\Actions\StageChipSubmission;
use App\Exceptions\RoboflowRequestException;
use App\Jobs\AnalyzeChipPhoto;
use App\Models\ChipSubmission;
use App\Models\Round;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));

    $keypair = sodium_crypto_sign_keypair();
    $publicKey = sodium_crypto_sign_publickey($keypair);
    $secretKey = sodium_crypto_sign_secretkey($keypair);

    config([
        'services.discord.public_key' => bin2hex($publicKey),
        'services.discord.chips_channel_id' => 'channel-1',
        'services.discord.test_secret_key' => $secretKey,
    ]);

    $this->postDiscord = function (array $payload): TestResponse {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = sodium_crypto_sign_detached(
            $timestamp.$body,
            config('services.discord.test_secret_key'),
        );

        return $this->call('POST', '/discord/interactions', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE_ED25519' => bin2hex($signature),
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
        ], $body);
    };
});

test('discord interactions require a valid signature', function () {
    $this->postJson('/discord/interactions', ['type' => 1])
        ->assertUnauthorized();
});

test('discord ping returns a pong', function () {
    ($this->postDiscord)(['type' => 1])
        ->assertOk()
        ->assertJson(['type' => 1]);
});

test('the fichas command opens a modal with the pending counts', function () {
    $player = User::factory()->create(['discord_id' => 'discord-1']);
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
        'chips' => 250,
        'chip_counts' => [100 => 2, 50 => 1, 25 => 0, 10 => 0, 5 => 0],
    ]);

    ($this->postDiscord)([
        'type' => 2,
        'channel_id' => 'channel-1',
        'member' => ['user' => ['id' => 'discord-1']],
        'data' => ['name' => 'fichas'],
    ])
        ->assertOk()
        ->assertJsonPath('type', 9)
        ->assertJsonPath('data.custom_id', 'chips_modal')
        ->assertJsonPath('data.components.0.components.0.value', '2')
        ->assertJsonPath('data.components.1.components.0.value', '1');
});

test('a modal submission stages the chip counts for the active round', function () {
    $player = User::factory()->create(['discord_id' => 'discord-1']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    ($this->postDiscord)([
        'type' => 5,
        'channel_id' => 'channel-1',
        'member' => ['user' => ['id' => 'discord-1']],
        'data' => [
            'custom_id' => 'chips_modal',
            'components' => [
                ['type' => 1, 'components' => [['custom_id' => 'chips_100', 'value' => '2']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_50', 'value' => '3']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_25', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_10', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_5', 'value' => '0']]],
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('type', 4);

    $submission = ChipSubmission::query()->firstOrFail();

    expect($submission->round_id)->toBe($round->id)
        ->and($submission->user_id)->toBe($player->id)
        ->and($submission->chips)->toBe(350)
        ->and($submission->chip_counts)->toBe([100 => 2, 50 => 3, 25 => 0, 10 => 0, 5 => 0])
        ->and($submission->source)->toBe('discord')
        ->and($submission->registered_at)->toBeNull();
});

test('submitting the modal again updates the same day submission', function () {
    $player = User::factory()->create(['discord_id' => 'discord-1']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    $payload = fn (string $hundreds): array => [
        'type' => 5,
        'channel_id' => 'channel-1',
        'member' => ['user' => ['id' => 'discord-1']],
        'data' => [
            'custom_id' => 'chips_modal',
            'components' => [
                ['type' => 1, 'components' => [['custom_id' => 'chips_100', 'value' => $hundreds]]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_50', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_25', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_10', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_5', 'value' => '0']]],
            ],
        ],
    ];

    ($this->postDiscord)($payload('2'))->assertOk();
    ($this->postDiscord)($payload('3'))->assertOk();

    expect(ChipSubmission::query()->count())->toBe(1)
        ->and(ChipSubmission::query()->first()->chips)->toBe(300);
});

test('resending after a legacy datetime submission updates instead of failing', function () {
    $player = User::factory()->create(['discord_id' => 'discord-1']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    DB::table('chip_submissions')->insert([
        'round_id' => $round->id,
        'user_id' => $player->id,
        'recorded_on' => '2026-11-06 00:00:00',
        'chips' => 200,
        'chip_counts' => json_encode([100 => 2, 50 => 0, 25 => 0, 10 => 0, 5 => 0]),
        'source' => 'discord',
        'submitted_at' => now(),
        'registered_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->postDiscord)([
        'type' => 5,
        'channel_id' => 'channel-1',
        'member' => ['user' => ['id' => 'discord-1']],
        'data' => [
            'custom_id' => 'chips_modal',
            'components' => [
                ['type' => 1, 'components' => [['custom_id' => 'chips_100', 'value' => '3']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_50', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_25', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_10', 'value' => '0']]],
                ['type' => 1, 'components' => [['custom_id' => 'chips_5', 'value' => '0']]],
            ],
        ],
    ])->assertOk();

    expect(ChipSubmission::query()->count())->toBe(1)
        ->and(ChipSubmission::query()->first()->chips)->toBe(300)
        ->and(DB::table('chip_submissions')->value('recorded_on'))->toBe('2026-11-06');
});

test('the vincular command links the discord account to the user', function () {
    $user = User::factory()->create();

    Cache::put('discord-link:ABC123', $user->id, now()->addMinutes(15));

    ($this->postDiscord)([
        'type' => 2,
        'channel_id' => 'channel-1',
        'member' => ['user' => ['id' => 'discord-9']],
        'data' => [
            'name' => 'vincular',
            'options' => [['name' => 'codigo', 'value' => 'abc123']],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('type', 4);

    expect($user->fresh()->discord_id)->toBe('discord-9')
        ->and(Cache::get('discord-link:ABC123'))->toBeNull();
});

test('the fichas-foto command acknowledges the interaction and dispatches the photo job', function () {
    Queue::fake();

    $player = User::factory()->create(['discord_id' => 'discord-1']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    ($this->postDiscord)([
        'type' => 2,
        'channel_id' => 'channel-1',
        'member' => ['user' => ['id' => 'discord-1']],
        'token' => 'interaction-token',
        'application_id' => 'app-1',
        'data' => [
            'name' => 'fichas-foto',
            'options' => [
                ['name' => 'foto', 'type' => 11, 'value' => 'attachment-1'],
            ],
            'resolved' => [
                'attachments' => [
                    'attachment-1' => [
                        'id' => 'attachment-1',
                        'url' => 'https://cdn.discordapp.com/attachments/1/2/chips.jpg',
                        'content_type' => 'image/jpeg',
                        'size' => 1024,
                        'filename' => 'chips.jpg',
                    ],
                ],
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('type', 5)
        ->assertJsonPath('data.flags', 64);

    Queue::assertPushed(AnalyzeChipPhoto::class, function (AnalyzeChipPhoto $job) use ($round, $player): bool {
        return $job->roundId === $round->id
            && $job->userId === $player->id
            && $job->date === '2026-11-06'
            && $job->attachmentUrl === 'https://cdn.discordapp.com/attachments/1/2/chips.jpg'
            && $job->mimeType === 'image/jpeg'
            && $job->interactionToken === 'interaction-token'
            && $job->applicationId === 'app-1';
    });

    expect(ChipSubmission::query()->count())->toBe(0);
});

test('the photo job counts the chips with Roboflow and stages the submission', function () {
    config([
        'services.roboflow.key' => 'test-key',
        'services.roboflow.model' => 'fichas-poker-r5t24/4',
    ]);

    $player = User::factory()->create(['discord_id' => 'discord-1']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    Http::fake([
        'cdn.discordapp.com/*' => Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']),
        'serverless.roboflow.com/*' => Http::response([
            'predictions' => [
                ['class' => 'Green Chip', 'confidence' => 0.9, 'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20],
                ['class' => 'Green Chip', 'confidence' => 0.8, 'x' => 40, 'y' => 10, 'width' => 20, 'height' => 20],
                ['class' => 'Black Chip', 'confidence' => 0.9, 'x' => 70, 'y' => 10, 'width' => 20, 'height' => 20],
                ['class' => 'White Chip', 'confidence' => 0.9, 'x' => 100, 'y' => 10, 'width' => 20, 'height' => 20],
                ['class' => 'White Chip', 'confidence' => 0.9, 'x' => 130, 'y' => 10, 'width' => 20, 'height' => 20],
                ['class' => 'White Chip', 'confidence' => 0.9, 'x' => 160, 'y' => 10, 'width' => 20, 'height' => 20],
                ['class' => 'Blue Chip', 'confidence' => 0.9, 'x' => 190, 'y' => 10, 'width' => 20, 'height' => 20],
                ['class' => 'Red Chip', 'confidence' => 0.9, 'x' => 220, 'y' => 10, 'width' => 20, 'height' => 20],
            ],
        ], 200),
        'discord.com/api/v10/webhooks/*' => Http::response([], 200),
    ]);

    $job = new AnalyzeChipPhoto(
        $round->id,
        $player->id,
        '2026-11-06',
        'https://cdn.discordapp.com/attachments/1/2/chips.jpg',
        'image/jpeg',
        'interaction-token',
        'app-1',
    );

    $job->handle(app(StageChipSubmission::class));

    $submission = ChipSubmission::query()->firstOrFail();

    expect($submission->chips)->toBe(310)
        ->and($submission->chip_counts)->toBe([100 => 2, 50 => 1, 25 => 1, 10 => 3, 5 => 1])
        ->and($submission->source)->toBe('discord')
        ->and($submission->registered_at)->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'serverless.roboflow.com/fichas-poker-r5t24/4')
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && str_contains($request->url(), 'confidence=0.4')
        && str_contains($request->url(), 'overlap=0.3')
        && $request->body() === base64_encode('fake-image'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && $request->url() === 'https://discord.com/api/v10/webhooks/app-1/interaction-token/messages/@original'
        && str_contains((string) data_get($request->data(), 'content'), '310'));
});

test('the photo job rethrows temporary Roboflow failures so the queue can retry', function () {
    config([
        'services.roboflow.key' => 'test-key',
        'services.roboflow.model' => 'fichas-poker-r5t24/4',
    ]);

    $player = User::factory()->create(['discord_id' => 'discord-1']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    Http::fake([
        'cdn.discordapp.com/*' => Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']),
        'serverless.roboflow.com/*' => Http::response([
            'error' => [
                'message' => 'The model is temporarily unavailable.',
                'code' => 'service_unavailable',
            ],
        ], 503),
        'discord.com/api/v10/webhooks/*' => Http::response([], 200),
    ]);

    $job = new AnalyzeChipPhoto(
        $round->id,
        $player->id,
        '2026-11-06',
        'https://cdn.discordapp.com/attachments/1/2/chips.jpg',
        'image/jpeg',
        'interaction-token',
        'app-1',
    );

    $job->withFakeQueueInteractions();

    expect(fn () => $job->handle(app(StageChipSubmission::class)))
        ->toThrow(RoboflowRequestException::class, 'Roboflow request failed: 503');

    expect(ChipSubmission::query()->count())->toBe(0)
        ->and($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([5, 15, 30]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && str_contains((string) data_get($request->data(), 'content'), 'temporariamente indisponível'));
});

test('the photo job reports high demand after transient Roboflow failures are exhausted', function () {
    Http::fake([
        'discord.com/api/v10/webhooks/*' => Http::response([], 200),
    ]);

    $job = new AnalyzeChipPhoto(
        1,
        1,
        '2026-11-06',
        'https://cdn.discordapp.com/attachments/1/2/chips.jpg',
        'image/jpeg',
        'interaction-token',
        'app-1',
    );

    $job->failed(new RoboflowRequestException(503, '{"error":{"code":"service_unavailable"}}'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && str_contains((string) data_get($request->data(), 'content'), 'alta demanda'));
});

test('the photo job sends a friendly error when Roboflow returns unmapped classes', function () {
    config([
        'services.roboflow.key' => 'test-key',
        'services.roboflow.model' => 'fichas-poker-r5t24/4',
    ]);

    $player = User::factory()->create(['discord_id' => 'discord-1']);
    $round = Round::factory()->create([
        'starts_on' => '2026-11-01',
        'total_chips' => 1000,
        'status' => 'ativa',
    ]);
    $round->players()->attach($player->id, ['chips' => 1000]);

    Http::fake([
        'cdn.discordapp.com/*' => Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']),
        'serverless.roboflow.com/*' => Http::response([
            'predictions' => [
                ['class' => 'Unknown Chip', 'confidence' => 0.9, 'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20],
            ],
        ], 200),
        'discord.com/api/v10/webhooks/*' => Http::response([], 200),
    ]);

    $job = new AnalyzeChipPhoto(
        $round->id,
        $player->id,
        '2026-11-06',
        'https://cdn.discordapp.com/attachments/1/2/chips.jpg',
        'image/jpeg',
        'interaction-token',
        'app-1',
    );

    $job->handle(app(StageChipSubmission::class));

    expect(ChipSubmission::query()->count())->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && str_contains((string) data_get($request->data(), 'content'), 'classes do modelo não estão mapeadas'));
});
