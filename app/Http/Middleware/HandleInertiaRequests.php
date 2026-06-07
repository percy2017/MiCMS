<?php

namespace App\Http\Middleware;

use App\Models\Media;
use App\Models\Setting;
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
        $site = Setting::site();

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
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
