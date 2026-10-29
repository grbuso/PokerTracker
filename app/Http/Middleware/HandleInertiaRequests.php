<?php

namespace App\Http\Middleware;

use App\Models\Round;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'sidebarRounds' => fn (): array => $request->user() === null
                ? []
                : Round::query()
                    ->latest('id')
                    ->get(['id', 'starts_on', 'status'])
                    ->map(fn (Round $round) => [
                        'id' => $round->id,
                        'starts_on' => $round->starts_on->format('d/m/Y'),
                        'status' => $round->status,
                    ])
                    ->all(),
        ];
    }
}
