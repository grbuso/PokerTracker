<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAdminAlert
{
    /**
     * Envia um alerta privado para os administradores do PokerTracker via DM do Discord.
     *
     * @param  array<string, mixed>  $context
     */
    public function handle(string $title, string $message, array $context = []): void
    {
        $botToken = (string) config('services.discord.bot_token');

        if ($botToken === '') {
            return;
        }

        $recipients = User::query()
            ->where('is_admin', true)
            ->whereNotNull('discord_id')
            ->where('discord_id', '!=', '')
            ->pluck('discord_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $configured = array_filter(
            array_map('trim', explode(',', (string) config('services.discord.admin_alert_user_ids', ''))),
        );

        $recipients = array_values(array_unique([...$recipients, ...$configured]));

        if ($recipients === []) {
            return;
        }

        $content = $this->format($title, $message, $context);

        foreach ($recipients as $discordId) {
            $this->sendTo($discordId, $content, $botToken);
        }
    }

    private function sendTo(string $discordId, string $content, string $botToken): void
    {
        try {
            $dm = Http::withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Accept' => 'application/json',
            ])->timeout(10)->post('https://discord.com/api/v10/users/@me/channels', [
                'recipient_id' => $discordId,
            ]);

            if ($dm->failed()) {
                Log::warning('Discord admin alert DM could not be opened', [
                    'discord_id' => $discordId,
                    'status' => $dm->status(),
                    'body' => $dm->body(),
                ]);

                return;
            }

            $channelId = $dm->json('id');

            if (! is_string($channelId) || $channelId === '') {
                Log::warning('Discord admin alert DM returned no channel id', [
                    'discord_id' => $discordId,
                ]);

                return;
            }

            $sent = Http::withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Accept' => 'application/json',
            ])->timeout(10)->post("https://discord.com/api/v10/channels/{$channelId}/messages", [
                'content' => $content,
            ]);

            if ($sent->failed()) {
                Log::warning('Discord admin alert message failed', [
                    'discord_id' => $discordId,
                    'status' => $sent->status(),
                    'body' => $sent->body(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function format(string $title, string $message, array $context): string
    {
        $lines = [
            "**🚨 {$title}**",
            '',
            $message,
        ];

        if ($context !== []) {
            $lines[] = '';

            foreach ($context as $key => $value) {
                $formatted = is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $lines[] = '`'.$key.'`: '.$formatted;
            }
        }

        return implode("\n", $lines);
    }
}
