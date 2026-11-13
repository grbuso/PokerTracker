<?php

namespace App\Http\Controllers;

use App\Actions\StageChipSubmission;
use App\Jobs\AnalyzeChipPhoto;
use App\Models\ChipSnapshot;
use App\Models\ChipSubmission;
use App\Models\Round;
use App\Models\User;
use App\Support\ChipCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DiscordInteractionController extends Controller
{
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
            'fichas-foto' => $this->handleChipsPhotoCommand($payload),
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
            ->latest('id')
            ->first();

        return $this->chipsModal($submission === null ? [] : ($submission->chip_counts ?? []));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleChipsPhotoCommand(array $payload): JsonResponse
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

        $attachment = $this->photoAttachment($payload);

        if ($attachment === null) {
            return $this->ephemeral(__('Anexe uma foto das suas fichas.'));
        }

        $url = $attachment['url'] ?? null;
        $mimeType = $attachment['content_type'] ?? '';
        $size = $attachment['size'] ?? null;

        if (! is_string($url) || $url === '') {
            return $this->ephemeral(__('Não consegui acessar a foto enviada. Tente novamente.'));
        }

        if (is_string($mimeType) && $mimeType !== '' && ! str_starts_with($mimeType, 'image/')) {
            return $this->ephemeral(__('Envie um arquivo de imagem (JPG, PNG ou WebP).'));
        }

        if (is_numeric($size) && (int) $size > 10 * 1024 * 1024) {
            return $this->ephemeral(__('A foto é muito grande. Envie uma imagem de até 10 MB.'));
        }

        $token = $payload['token'] ?? null;
        $applicationId = $payload['application_id'] ?? config('services.discord.application_id');

        if (! is_string($token) || $token === '' || ! is_string($applicationId) || $applicationId === '') {
            return $this->ephemeral(__('Não consegui iniciar a análise da foto. Tente novamente.'));
        }

        try {
            AnalyzeChipPhoto::dispatch(
                $round->id,
                $user->id,
                today()->toDateString(),
                $url,
                is_string($mimeType) ? $mimeType : '',
                $token,
                $applicationId,
            )->delay(now()->addSeconds(2));
        } catch (\Throwable $exception) {
            report($exception);

            return $this->ephemeral(__('Não foi possível iniciar a análise agora. Tente novamente em instantes.'));
        }

        return response()->json([
            'type' => 5,
            'data' => [
                'flags' => 64,
            ],
        ]);
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

        $total = ChipCounts::total($counts);

        try {
            app(StageChipSubmission::class)->handle($round, $user, today()->toDateString(), $counts, $total);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->ephemeral(__('Não foi possível salvar suas fichas agora. Tente novamente em instantes.'));
        }

        return $this->ephemeral(__('Fichas enviadas: :breakdown = :total fichas. Aguardando o registro do dia.', [
            'breakdown' => ChipCounts::breakdown($counts),
            'total' => number_format($total, 0, ',', '.'),
        ]));
    }

    /**
     * @param  array<int, int>  $counts
     */
    private function chipsModal(array $counts): JsonResponse
    {
        $components = [];

        foreach (ChipCounts::DENOMINATIONS as $denomination) {
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

        foreach (ChipCounts::DENOMINATIONS as $denomination) {
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
     * @return array<string, mixed>|null
     */
    private function photoAttachment(array $payload): ?array
    {
        $attachmentId = $this->commandOption($payload, 'foto');

        if ($attachmentId === null) {
            return null;
        }

        $attachment = $payload['data']['resolved']['attachments'][$attachmentId] ?? null;

        return is_array($attachment) ? $attachment : null;
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
