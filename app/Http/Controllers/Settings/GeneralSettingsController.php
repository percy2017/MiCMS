<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Models\Media;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorize('view settings');

        $logoId = Setting::get('site_logo');
        $logo = $logoId ? Media::query()->find($logoId) : null;

        return Inertia::render('settings/general', [
            'settings' => [
                'site_name' => (string) (Setting::get('site_name') ?? config('app.name')),
                'site_tagline' => (string) (Setting::get('site_tagline') ?? ''),
                'site_logo_id' => $logoId,
                'site_logo_url' => $logo?->url(),
            ],
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->authorize('update settings');

        $data = $request->validated();

        Setting::set('site_name', (string) $data['site_name'], Setting::TYPE_STRING);
        Setting::set('site_tagline', (string) ($data['site_tagline'] ?? ''), Setting::TYPE_TEXT);

        if (array_key_exists('site_logo_id', $data)) {
            $logoId = $data['site_logo_id'] !== null ? (int) $data['site_logo_id'] : null;
            Setting::set('site_logo', $logoId, Setting::TYPE_INTEGER);
        } else {
            Setting::set('site_logo', null);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ajustes guardados.')]);

        return to_route('settings.general.edit');
    }
}
