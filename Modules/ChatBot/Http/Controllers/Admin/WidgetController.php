<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Http\Requests\UpdateWidgetRequest;
use Modules\ChatBot\Models\Channel;

class WidgetController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('update chatbot widget'), 403);

        $channel = Channel::create([
            'type' => 'web_widget',
            'name' => 'Widget Web',
            'enabled' => true,
            'settings' => [],
        ]);

        return redirect()->route('chatbot.admin.widget')
            ->with('success', 'Widget Web creado.');
    }
    private function getChannel(): Channel
    {
        $channel = Channel::where('type', 'web_widget')->first();

        if (! $channel) {
            $channel = Channel::create([
                'type' => 'web_widget',
                'name' => 'Widget Web',
                'enabled' => true,
                'settings' => [],
            ]);
        }

        return $channel;
    }

    public function edit(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $channel = $this->getChannel();
        $settings = $channel->settings ?? [];

        return Inertia::render('ChatBot::Widget', [
            'widget' => [
                'id' => $channel->id,
                'enabled' => $channel->enabled,
                'title' => $settings['title'] ?? 'Asistente virtual',
                'subtitle' => $settings['subtitle'] ?? 'Te respondemos en minutos',
                'greeting' => $settings['greeting'] ?? '¡Hola! ¿En qué podemos ayudarte?',
                'position' => $settings['position'] ?? 'right',
                'avatar_media_id' => $settings['avatar_media_id'] ?? null,
                'avatar_url' => null,
                'require_auth' => $settings['require_auth'] ?? true,
                'show_typing' => $settings['show_typing'] ?? true,
                'offline_message' => $settings['offline_message'] ?? null,
            ],
        ]);
    }

    public function update(UpdateWidgetRequest $request)
    {
        $channel = $this->getChannel();
        $data = $request->validated();

        $settings = $channel->settings ?? [];
        foreach (['title', 'subtitle', 'greeting', 'position', 'avatar_media_id', 'require_auth', 'show_typing', 'offline_message'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = $data[$key];
            }
        }

        $channel->update([
            'enabled' => $data['enabled'] ?? $channel->enabled,
            'settings' => $settings,
        ]);

        return back()->with('success', 'Configuración del widget actualizada.');
    }
}
