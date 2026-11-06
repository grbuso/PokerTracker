<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('discord:register-commands')]
#[Description('Register the Discord slash commands used by the chip submissions bot.')]
class RegisterDiscordCommands extends Command
{
    public function handle(): int
    {
        $applicationId = (string) config('services.discord.application_id');
        $guildId = (string) config('services.discord.guild_id');
        $botToken = (string) config('services.discord.bot_token');

        if ($applicationId === '' || $botToken === '') {
            $this->error('Configure DISCORD_APPLICATION_ID and DISCORD_BOT_TOKEN first.');

            return self::FAILURE;
        }

        $url = $guildId !== ''
            ? "https://discord.com/api/v10/applications/{$applicationId}/guilds/{$guildId}/commands"
            : "https://discord.com/api/v10/applications/{$applicationId}/commands";

        $response = Http::withToken($botToken)->put($url, [
            [
                'name' => 'fichas',
                'description' => 'Envia as suas fichas do dia',
                'type' => 1,
            ],
            [
                'name' => 'vincular',
                'description' => 'Vincula a sua conta do app ao Discord',
                'type' => 1,
                'options' => [
                    [
                        'type' => 3,
                        'name' => 'codigo',
                        'description' => 'Código gerado no seu perfil do app',
                        'required' => true,
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            $this->error('Discord rejected the command registration:');
            $this->line($response->body());

            return self::FAILURE;
        }

        $this->info('Discord commands registered successfully.');

        return self::SUCCESS;
    }
}
