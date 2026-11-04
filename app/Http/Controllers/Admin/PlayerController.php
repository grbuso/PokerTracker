<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlayerRequest;
use App\Http\Requests\Admin\UpdatePlayerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    /**
     * Show the player management page.
     */
    public function index(Request $request): Response
    {
        $players = User::query()
            ->withCount('rounds')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_admin', 'avatar_path'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'avatar' => $user->avatar,
                'rounds_count' => $user->rounds_count,
                'is_current_user' => $user->is($request->user()),
            ]);

        return Inertia::render('admin/Players', [
            'players' => $players,
        ]);
    }

    /**
     * Create a new player account.
     */
    public function store(StorePlayerRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'password']);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $player = User::create($data);

        $player->forceFill([
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Jogador criado com sucesso.'),
        ]);

        return to_route('admin.players.index');
    }

    /**
     * Update an existing player account.
     */
    public function update(UpdatePlayerRequest $request, User $player): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        $oldAvatarPath = $player->avatar_path;

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $player->update($data);

        if ($oldAvatarPath !== null && $oldAvatarPath !== $player->avatar_path) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Jogador atualizado com sucesso.'),
        ]);

        return to_route('admin.players.index');
    }

    /**
     * Delete a player account.
     */
    public function destroy(Request $request, User $player): RedirectResponse
    {
        if ($player->is($request->user()) || $player->is_admin) {
            abort(403);
        }

        if ($player->rounds()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Jogador com histórico de rodadas não pode ser removido.'),
            ]);

            return back();
        }

        if ($player->avatar_path !== null) {
            Storage::disk('public')->delete($player->avatar_path);
        }

        $player->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Jogador removido com sucesso.'),
        ]);

        return to_route('admin.players.index');
    }
}
