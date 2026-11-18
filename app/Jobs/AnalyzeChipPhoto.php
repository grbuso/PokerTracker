<?php

namespace App\Jobs;

use App\Actions\StageChipSubmission;
use App\Exceptions\RoboflowRequestException;
use App\Models\ChipSnapshot;
use App\Models\Round;
use App\Models\User;
use App\Support\ChipCounts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AnalyzeChipPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 30];

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

        $apiKey = (string) config('services.roboflow.key');
        $model = trim((string) config('services.roboflow.model'));

        if ($apiKey === '' || $model === '') {
            report(new RuntimeException('Roboflow is not configured.'));

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

            $confidence = (float) config('services.roboflow.confidence', 0.4);
            $overlap = (float) config('services.roboflow.overlap', 0.3);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/json',
            ])->timeout(90)
                ->withBody(base64_encode($body), 'application/x-www-form-urlencoded')
                ->post('https://serverless.roboflow.com/'.ltrim($model, '/').'?'.http_build_query([
                    'confidence' => $confidence,
                    'overlap' => $overlap,
                ]));

            if ($response->failed()) {
                throw new RoboflowRequestException($response->status(), $response->body());
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new RuntimeException('Roboflow returned an invalid response payload.');
            }

            $counts = $this->countPredictions($payload, $confidence);
            $total = ChipCounts::total($counts);

            if ($total <= 0) {
                throw new RuntimeException('Roboflow did not identify any chips.');
            }

            $stageSubmission->handle($round, $user, $this->date, $counts, $total, 'discord', $this->attachmentUrl);
        } catch (Throwable $exception) {
            report($exception);

            if ($this->isTransientFailure($exception)) {
                if ($this->job !== null && $this->attempts() === 1) {
                    $this->editOriginalResponse(__('O serviço de IA está temporariamente indisponível. Ainda estou tentando contar as fichas...'));
                }

                throw $exception;
            }

            $this->respondWithError($this->failureMessage($exception));

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
        if ($exception !== null && ! $this->isTransientFailure($exception)) {
            report($exception);
        }

        $this->respondWithError($this->failureMessage($exception));
    }

    private function isTransientFailure(?Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RoboflowRequestException && $exception->isTransient());
    }

    private function failureMessage(?Throwable $exception): string
    {
        if ($exception instanceof RoboflowRequestException) {
            if ($exception->status === 429) {
                return __('O limite de uso da IA foi atingido. Tente novamente em alguns minutos ou use /fichas para enviar manualmente.');
            }

            if (in_array($exception->status, [401, 403], true)) {
                return __('A chave da IA foi recusada. Confira a configuração do servidor ou use /fichas para enviar manualmente.');
            }

            if ($exception->status === 404) {
                return __('O modelo de IA não foi encontrado. Confira a versão configurada no servidor ou use /fichas para enviar manualmente.');
            }

            if (in_array($exception->status, [400, 422], true)) {
                return __('O Roboflow recusou a requisição. Confira o modelo/versão e a configuração do servidor ou use /fichas para enviar manualmente.');
            }
        }

        if ($this->isTransientFailure($exception)) {
            return __('O serviço de IA está com alta demanda ou indisponível agora. Tente novamente em alguns minutos ou use /fichas para enviar manualmente.');
        }

        $message = $exception?->getMessage() ?? '';

        if (str_contains($message, 'unmapped classes')) {
            return __('A IA identificou fichas, mas as classes do modelo não estão mapeadas. Use /fichas para enviar manualmente.');
        }

        if (str_contains($message, 'classification response')) {
            return __('O modelo de IA configurado não é de detecção de objetos. Use /fichas para enviar manualmente.');
        }

        if (str_contains($message, 'did not identify any chips')) {
            return __('Não identifiquei fichas nesta foto. Tente outra foto ou use /fichas para enviar manualmente.');
        }

        if (str_contains($message, 'invalid response payload')) {
            return __('O Roboflow retornou uma resposta inesperada. Use /fichas para enviar manualmente.');
        }

        if (str_contains($message, 'Could not download')) {
            return __('Não consegui baixar a foto enviada. Tente novamente ou use /fichas para enviar manualmente.');
        }

        return __('Não consegui analisar a foto. Tente novamente ou use /fichas para enviar manualmente.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    private function countPredictions(array $payload, float $minimumConfidence): array
    {
        $predictions = $payload['predictions'] ?? null;

        if (! is_array($predictions) || ! array_is_list($predictions)) {
            throw new RuntimeException('Roboflow returned an invalid response payload.');
        }

        $counts = array_fill_keys(ChipCounts::DENOMINATIONS, 0);
        $unknownClasses = [];
        $hasBoundingBoxes = false;

        foreach ($predictions as $prediction) {
            if (! is_array($prediction)) {
                continue;
            }

            if (isset($prediction['x'], $prediction['y'], $prediction['width'], $prediction['height'])) {
                $hasBoundingBoxes = true;
            }

            $class = $prediction['class'] ?? null;
            $confidence = $prediction['confidence'] ?? null;

            if (! is_string($class) || trim($class) === '') {
                continue;
            }

            if (is_numeric($confidence) && (float) $confidence < $minimumConfidence) {
                continue;
            }

            $denomination = $this->denominationFor($class);

            if ($denomination === null) {
                $unknownClasses[] = $class;

                continue;
            }

            $counts[$denomination]++;
        }

        $this->logPredictions($predictions, $counts, $unknownClasses, $hasBoundingBoxes, $minimumConfidence);

        if ($predictions !== [] && ! $hasBoundingBoxes) {
            throw new RuntimeException('Roboflow returned a classification response; an object detection model is required.');
        }

        if (array_sum($counts) > 0) {
            return $counts;
        }

        if ($unknownClasses !== []) {
            throw new RuntimeException('Roboflow returned unmapped classes: '.implode(', ', array_unique($unknownClasses)));
        }

        throw new RuntimeException('Roboflow did not identify any chips.');
    }

    /**
     * @param  list<mixed>  $predictions
     * @param  array<int, int>  $counts
     * @param  list<string>  $unknownClasses
     */
    private function logPredictions(array $predictions, array $counts, array $unknownClasses, bool $hasBoundingBoxes, float $minimumConfidence): void
    {
        Log::info('Roboflow chip predictions', [
            'model' => (string) config('services.roboflow.model'),
            'configured_confidence' => (float) config('services.roboflow.confidence', 0.4),
            'configured_overlap' => (float) config('services.roboflow.overlap', 0.3),
            'minimum_confidence' => $minimumConfidence,
            'prediction_count' => count($predictions),
            'detections' => array_map(static function (mixed $prediction): array {
                if (! is_array($prediction)) {
                    return ['invalid' => true];
                }

                $confidence = $prediction['confidence'] ?? null;

                return [
                    'class' => $prediction['class'] ?? null,
                    'confidence' => is_numeric($confidence) ? round((float) $confidence, 4) : null,
                    'x' => $prediction['x'] ?? null,
                    'y' => $prediction['y'] ?? null,
                    'width' => $prediction['width'] ?? null,
                    'height' => $prediction['height'] ?? null,
                ];
            }, $predictions),
            'counts' => $counts,
            'unknown_classes' => array_values(array_unique($unknownClasses)),
            'has_bounding_boxes' => $hasBoundingBoxes,
        ]);
    }

    private function denominationFor(string $class): ?int
    {
        $normalized = strtolower(trim($class));
        $compact = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
        $spaced = trim(preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '');

        $aliases = [
            'blackchip' => 50,
            'greenchip' => 100,
            'whitechip' => 10,
            'redchip' => 5,
            'bluechip' => 25,
            'black' => 50,
            'preto' => 50,
            'preta' => 50,
            'green' => 100,
            'verde' => 100,
            'white' => 10,
            'branco' => 10,
            'branca' => 10,
            'red' => 5,
            'vermelho' => 5,
            'vermelha' => 5,
            'blue' => 25,
            'azul' => 25,
        ];

        if (isset($aliases[$compact])) {
            return $aliases[$compact];
        }

        foreach (ChipCounts::DENOMINATIONS as $denomination) {
            if (preg_match('/(?<!\d)'.$denomination.'(?!\d)/', $spaced) === 1) {
                return $denomination;
            }
        }

        foreach ($aliases as $alias => $denomination) {
            if (str_contains($compact, $alias)) {
                return $denomination;
            }
        }

        return null;
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
            $payload = ['content' => $content];

            if ($this->attachmentUrl !== '') {
                $payload['embeds'] = [[
                    'image' => ['url' => $this->attachmentUrl],
                ]];
            }

            $response = Http::timeout(15)->patch(
                "https://discord.com/api/v10/webhooks/{$this->applicationId}/{$this->interactionToken}/messages/@original",
                $payload,
            );

            if ($response->failed()) {
                report(new RuntimeException('Discord follow-up failed: '.$response->status().' '.$response->body()));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
