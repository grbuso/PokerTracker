<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DiscordLinkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $token = Str::upper(Str::random(8));
        $expiresAt = now()->addMinutes(15);

        Cache::put('discord-link:'.$token, $request->user()->id, $expiresAt);

        return to_route('profile.edit')
            ->with('discord_link_token', $token)
            ->with('discord_link_expires_at', $expiresAt->toIso8601String());
    }
}
