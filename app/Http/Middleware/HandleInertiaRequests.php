<?php

namespace App\Http\Middleware;

use App\Models\Media;
use App\Models\Setting;
use App\Services\PackageManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $site = Setting::site();
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => $site['site_name'] ?? config('app.name'),
            'site' => [
                'name' => $site['site_name'] ?? config('app.name'),
                'tagline' => $site['site_tagline'] ?? '',
                'logo_url' => $site['site_logo'] ? Media::find($site['site_logo'])?->url() : null,
            ],
            'appearance' => $request->user() ? null : 'light',
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->all(),
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'enabledPackages' => fn (): array => $user
                ? app(PackageManager::class)->enabled()
                    ->map(fn (array $pkg): array => [
                        'slug' => $pkg['slug'],
                        'label' => $pkg['menu_label'],
                        'icon' => $pkg['icon'],
                        'menu' => $pkg['menu'],
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }
}
