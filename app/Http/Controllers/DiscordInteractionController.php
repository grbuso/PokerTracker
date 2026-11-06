<?php

namespace App\Http\Controllers;

use App\Models\ChipSnapshot;
use App\Models\ChipSubmission;
use App\Models\Round;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DiscordInteractionController extends Controller
{
    /**
     * @var list<int>
     */
    private const array DENOMINATIONS = [100, 50, 25, 10, 5];

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();

        return match ($payload['type'] ?? null) {
            1 => response()->json(['type' => 1]),
            2 => $this->handleCommand($payload),
            5 => $this->handleModalSubmit($payload),
            default => $this->ephemeral(__('Interação não suportada.')),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCommand(array $payload): JsonResponse
    {
        if (! $this->isAllowedChannel($payload)) {
            return $this->ephemeral(__('Use este comando no canal de fichas.'));
        }

        return match ($payload['data']['name'] ?? null) {
            'fichas' => $this->handleChipsCommand($payload),
            'vincular' => $this->handleLinkCommand($payload),
            default => $this->ephemeral(__('Comando desconhecido.')),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleChipsCommand(array $payload): JsonResponse
    {
        $user = $this->linkedUser($payload);

        if ($user === null) {
            return $this->notLinkedMessage();
        }

        $round = $this->activeRound();

        if ($round === null) {
            return $this->ephemeral(__('Não há rodada ativa no momento.'));
        }

        if (! today()->isWeekday()) {
            return $this->ephemeral(__('Hoje não é um dia útil de jogo.'));
        }

        if (! $this->isActivePlayer($round, $user)) {
            return $this->ephemeral(__('Você não é um jogador ativo desta rodada.'));
        }

        if ($this->dayWasRegistered($round)) {
            return $this->ephemeral(__('O dia de hoje já foi registrado.'));
        }

        $submission = ChipSubmission::query()
            ->where('round_id', $round->id)
            ->where('user_id', $user->id)
            ->whereDate('recorded_on', today()->toDateString())
            ->whereNull('registered_at')
            ->first();

        return $this->chipsModal($submission === null ? [] : ($submission->chip_counts ?? []));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleLinkCommand(array $payload): JsonResponse
    {
        $discordId = $this->discordUserId($payload);
        $code = $this->commandOption($payload, 'codigo');

        if ($discordId === null || $code === null || $code === '') {
            return $this->ephemeral(__('Informe o código gerado no app.'));
        }

        $userId = Cache::pull('discord-link:'.strtoupper($code));

        if (! is_int($userId)) {
            return $this->ephemeral(__('Código inválido ou expirado. Gere outro no app.'));
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return $this->ephemeral(__('Conta não encontrada.'));
        }

        $alreadyLinked = User::query()
            ->where('discord_id', $discordId)
            ->whereKeyNot($user->id)
            ->exists();

        if ($alreadyLinked) {
            return $this->ephemeral(__('Este Discord já está vinculado a outra conta.'));
        }

        $user->update(['discord_id' => $discordId]);

        return $this->ephemeral(__('Conta vinculada com sucesso! Use /fichas para enviar suas fichas.'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleModalSubmit(array $payload): JsonResponse
    {
        if (($payload['data']['custom_id'] ?? null) !== 'chips_modal') {
            return $this->ephemeral(__('Formulário desconhecido.'));
        }

        if (! $this->isAllowedChannel($payload)) {
            return $this->ephemeral(__('Use este comando no canal de fichas.'));
        }

        $user = $this->linkedUser($payload);

        if ($user === null) {
            return $this->notLinkedMessage();
        }

        $round = $this->activeRound();

        if ($round === null) {
            return $this->ephemeral(__('Não há rodada ativa no momento.'));
        }

        if (! today()->isWeekday()) {
            return $this->ephemeral(__('Hoje não é um dia útil de jogo.'));
        }

        if (! $this->isActivePlayer($round, $user)) {
            return $this->ephemeral(__('Você não é um jogador ativo desta rodada.'));
        }

        if ($this->dayWasRegistered($round)) {
            return $this->ephemeral(__('O dia de hoje já foi registrado.'));
        }

        $counts = $this->countsFromPayload($payload);

        if ($counts === null) {
            return $this->ephemeral(__('Informe quantidades inteiras e válidas de fichas.'));
        }

        $total = $this->totalFromCounts($counts);

        ChipSubmission::query()->updateOrCreate(
            [
                'round_id' => $round->id,
                'user_id' => $user->id,
                'recorded_on' => today()->toDateString(),
            ],
            [
                'chips' => $total,
                'chip_counts' => $counts,
                'source' => 'discord',
                'submitted_at' => now(),
                'registered_at' => null,
            ],
        );

        return $this->ephemeral(__('Fichas enviadas: :breakdown = :total fichas. Aguardando o registro do dia.', [
            'breakdown' => $this->breakdown($counts),
            'total' => number_format($total, 0, ',', '.'),
        ]));
    }

    /**
     * @param  array<int, int>  $counts
     */
    private function chipsModal(array $counts): JsonResponse
    {
        $components = [];

        foreach (self::DENOMINATIONS as $denomination) {
            $input = [
                'type' => 4,
                'custom_id' => 'chips_'.$denomination,
                'label' => 'Fichas de '.$denomination,
                'style' => 1,
                'required' => false,
                'max_length' => 5,
            ];

            $value = $counts[$denomination] ?? null;

            if ($value !== null) {
                $input['value'] = (string) $value;
            }

            $components[] = [
                'type' => 1,
                'components' => [$input],
            ];
        }

        return response()->json([
            'type' => 9,
            'data' => [
                'custom_id' => 'chips_modal',
                'title' => 'Enviar fichas do dia',
                'components' => $components,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>|null
     */
    private function countsFromPayload(array $payload): ?array
    {
        $values = [];

        foreach ($payload['data']['components'] ?? [] as $row) {
            foreach ($row['components'] ?? [] as $component) {
                $values[$component['custom_id'] ?? ''] = $component['value'] ?? '0';
            }
        }

        $counts = [];

        foreach (self::DENOMINATIONS as $denomination) {
            $raw = $values['chips_'.$denomination] ?? '0';
            $count = filter_var($raw, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);

            if ($count === false) {
                return null;
            }

            $counts[$denomination] = $count;
        }

        return $counts;
    }

    /**
     * @param  array<int, int>  $counts
     */
    private function totalFromCounts(array $counts): int
    {
        $total = 0;

        foreach (self::DENOMINATIONS as $denomination) {
            $total += ($counts[$denomination] ?? 0) * $denomination;
        }

        return $total;
    }

    /**
     * @param  array<int, int>  $counts
     */
    private function breakdown(array $counts): string
    {
        $parts = [];

        foreach (self::DENOMINATIONS as $denomination) {
            $count = $counts[$denomination] ?? 0;

            if ($count > 0) {
                $parts[] = $count.'×'.$denomination;
            }
        }

        return $parts === [] ? '0 fichas' : implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function linkedUser(array $payload): ?User
    {
        $discordId = $this->discordUserId($payload);

        if ($discordId === null) {
            return null;
        }

        return User::query()->where('discord_id', $discordId)->first();
    }

    private function activeRound(): ?Round
    {
        return Round::query()->where('status', 'ativa')->latest('id')->first();
    }

    private function isActivePlayer(Round $round, User $user): bool
    {
        return $round->players()
            ->whereKey($user->id)
            ->wherePivotNull('eliminated_at')
            ->exists();
    }

    private function dayWasRegistered(Round $round): bool
    {
        return ChipSnapshot::query()
            ->where('round_id', $round->id)
            ->whereDate('recorded_on', today()->toDateString())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function discordUserId(array $payload): ?string
    {
        $id = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function commandOption(array $payload, string $name): ?string
    {
        foreach ($payload['data']['options'] ?? [] as $option) {
            if (($option['name'] ?? null) === $name) {
                $value = $option['value'] ?? null;

                return is_string($value) ? $value : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isAllowedChannel(array $payload): bool
    {
        $configuredChannel = (string) config('services.discord.chips_channel_id');

        if ($configuredChannel === '') {
            return true;
        }

        return ($payload['channel_id'] ?? null) === $configuredChannel;
    }

    private function notLinkedMessage(): JsonResponse
    {
        return $this->ephemeral(__('Sua conta ainda não está vinculada. Gere um código no app e use /vincular.'));
    }

    private function ephemeral(string $content): JsonResponse
    {
        return response()->json([
            'type' => 4,
            'data' => [
                'content' => $content,
                'flags' => 64,
            ],
        ]);
    }

    private function signatureIsValid(Request $request): bool
    {
        $publicKey = (string) config('services.discord.public_key');
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');

        if ($publicKey === '' || ! is_string($signature) || ! is_string($timestamp)) {
            return false;
        }

        $publicKeyBinary = hex2bin($publicKey);
        $signatureBinary = hex2bin($signature);

        if ($publicKeyBinary === false || $signatureBinary === false) {
            return false;
        }

        if ($publicKeyBinary === '' || $signatureBinary === '') {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $signatureBinary,
            $timestamp.$request->getContent(),
            $publicKeyBinary,
        );
    }
}
