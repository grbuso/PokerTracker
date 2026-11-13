<?php

namespace App\Jobs;

use App\Actions\StageChipSubmission;
use App\Models\ChipSnapshot;
use App\Models\Round;
use App\Models\User;
use App\Support\ChipCounts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AnalyzeChipPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(
        public int $roundId,
        public int $userId,
        public string $date,
        public string $attachmentUrl,
        public string $mimeType,
        public string $interactionToken,
        public string $applicationId,
    ) {}

    public function handle(StageChipSubmission $stageSubmission): void
    {
        $round = Round::query()->find($this->roundId);
        $user = User::query()->find($this->userId);

        if ($round === null || $user === null) {
            $this->respondWithError(__('Não encontrei a rodada ou o jogador desta foto. Tente novamente ou use /fichas para enviar manualmente.'));

            return;
        }

        if ($round->status !== 'ativa') {
            $this->respondWithError(__('A rodada não está mais ativa. Use /fichas para enviar manualmente se ainda precisar.'));

            return;
        }

        if (! $round->players()->whereKey($user->id)->wherePivotNull('eliminated_at')->exists()) {
            $this->respondWithError(__('Você não é mais um jogador ativo desta rodada.'));

            return;
        }

        if (ChipSnapshot::query()->where('round_id', $round->id)->whereDate('recorded_on', $this->date)->exists()) {
            $this->respondWithError(__('O dia desta foto já foi registrado. Use /fichas para ver os detalhes.'));

            return;
        }

        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if ($apiKey === '' || $model === '') {
            report(new RuntimeException('Gemini is not configured.'));

            $this->respondWithError(__('A análise por foto não está configurada no servidor. Use /fichas para enviar manualmente.'));

            return;
        }

        $counts = null;
        $total = 0;

        try {
            $image = Http::timeout(30)->get($this->attachmentUrl);

            if ($image->failed()) {
                throw new RuntimeException('Could not download the Discord attachment: '.$image->status());
            }

            $body = $image->body();

            if ($body === '' || strlen($body) > 10 * 1024 * 1024) {
                throw new RuntimeException('The Discord attachment is empty or too large.');
            }

            $mimeType = $this->mimeType !== '' ? $this->mimeType : (string) $image->header('Content-Type');
            $mimeType = strtolower(trim(explode(';', $mimeType)[0]));

            if (! str_starts_with($mimeType, 'image/')) {
                throw new RuntimeException('The Discord attachment is not an image.');
            }

            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Api-Revision' => '2026-05-20',
            ])->timeout(90)->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                'model' => $model,
                'store' => false,
                'input' => [
                    ['type' => 'text', 'text' => $this->prompt()],
                    ['type' => 'image', 'data' => base64_encode($body), 'mime_type' => $mimeType],
                ],
                'response_format' => [
                    'type' => 'text',
                    'mime_type' => 'application/json',
                    'schema' => $this->schema(),
                ],
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Gemini request failed: '.$response->status().' '.$response->body());
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new RuntimeException('Gemini returned an invalid response payload.');
            }

            $text = $this->extractText($payload);

            if ($text === null) {
                throw new RuntimeException('Gemini did not return a text response.');
            }

            $data = $this->decodeJson($text);
            $counts = ChipCounts::normalize($data['counts'] ?? null);

            if ($counts === null) {
                throw new RuntimeException('Gemini returned invalid chip counts.');
            }

            $total = ChipCounts::total($counts);

            if ($total <= 0) {
                throw new RuntimeException('Gemini did not identify any chips.');
            }

            $stageSubmission->handle($round, $user, $this->date, $counts, $total);
        } catch (Throwable $exception) {
            report($exception);

            $this->respondWithError(__('Não consegui processar as fichas desta foto. Tente novamente ou use /fichas para enviar manualmente.'));

            return;
        }

        if ($counts === null) {
            $this->respondWithError(__('Não consegui processar as fichas desta foto. Tente novamente ou use /fichas para enviar manualmente.'));

            return;
        }

        $message = __('Fichas lidas pela IA: :breakdown = :total fichas.', [
            'breakdown' => ChipCounts::breakdown($counts),
            'total' => number_format($total, 0, ',', '.'),
        ]);

        $message .= "\n".__('Se algo estiver errado, use /fichas para corrigir antes do registro do dia.');

        $this->editOriginalResponse($message);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }

        $this->respondWithError(__('Não consegui analisar a foto. Tente novamente ou use /fichas para enviar manualmente.'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): ?string
    {
        if (is_string($payload['output_text'] ?? null)) {
            return $payload['output_text'];
        }

        $steps = $payload['steps'] ?? null;

        if (! is_array($steps)) {
            return null;
        }

        foreach ($steps as $step) {
            if (! is_array($step) || ($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            $contents = $step['content'] ?? null;

            if (! is_array($contents)) {
                continue;
            }

            foreach ($contents as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $text): array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)```/s', $text, $matches) === 1) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) && preg_match('/\{.*\}/s', $text, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Could not decode the Gemini JSON response.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'counts' => [
                    'type' => 'object',
                    'properties' => [
                        '100' => ['type' => 'integer'],
                        '50' => ['type' => 'integer'],
                        '25' => ['type' => 'integer'],
                        '10' => ['type' => 'integer'],
                        '5' => ['type' => 'integer'],
                    ],
                    'required' => ['100', '50', '25', '10', '5'],
                ],
                'confidence' => ['type' => 'number'],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['counts', 'confidence', 'notes'],
        ];
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Você está analisando uma foto de fichas de poker.

Conte as fichas visíveis usando apenas as cores abaixo:

- Verde = 100
- Preta = 50
- Azul = 25
- Branca = 10
- Vermelha = 5

Todas as cores, exceto o verde, podem aparecer em dois formatos físicos diferentes:
um maior com listras laterais e outro menor e liso. Os dois formatos da mesma cor
valem o mesmo e devem ser somados na mesma contagem.

Regras:
- Conte cada ficha visível na imagem.
- Não conte a mesma ficha mais de uma vez.
- Considere a imagem inteira, inclusive as bordas.
- Não invente fichas escondidas em pilhas; conte apenas o que estiver visível.
- Ignore o fundo da mesa e quaisquer objetos que não sejam fichas.
- Se não houver nenhuma ficha de uma cor, use 0.
- Responda apenas com o JSON solicitado, sem explicações fora do JSON.

Retorne as quantidades por valor de ficha no campo "counts", usando as chaves
"100", "50", "25", "10" e "5".
PROMPT;
    }

    private function respondWithError(string $message): void
    {
        $this->editOriginalResponse($message);
    }

    private function editOriginalResponse(string $content): void
    {
        if ($this->applicationId === '' || $this->interactionToken === '') {
            return;
        }

        try {
            $response = Http::timeout(15)->patch(
                "https://discord.com/api/v10/webhooks/{$this->applicationId}/{$this->interactionToken}/messages/@original",
                ['content' => $content],
            );

            if ($response->failed()) {
                report(new RuntimeException('Discord follow-up failed: '.$response->status().' '.$response->body()));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
