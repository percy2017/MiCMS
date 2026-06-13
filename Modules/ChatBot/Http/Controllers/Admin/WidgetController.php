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
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $widgets = Channel::where('type', 'web_widget')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (Channel $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'enabled' => $c->enabled,
                'title' => $c->settings['title'] ?? 'Asistente virtual',
                'public_key' => $c->public_key,
                'allowed_domains' => $c->allowed_domains ?? [],
                'conversations_count' => $c->conversations()->count(),
            ]);

        return Inertia::render('ChatBot::Widget/Index', [
            'widgets' => $widgets,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        return Inertia::render('ChatBot::Widget/Edit', [
            'widget' => [
                'id' => null,
                'is_new' => true,
                'enabled' => true,
                'name' => '',
                'title' => 'Asistente virtual',
                'subtitle' => 'Te respondemos en minutos',
                'greeting' => '¡Hola! ¿En qué podemos ayudarte?',
                'position' => 'right',
                'avatar_media_id' => null,
                'avatar_url' => null,
                'require_auth' => true,
                'show_typing' => true,
                'offline_message' => null,
                'allowed_domains' => [],
                'public_key' => null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('view chatbot'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'enabled' => ['boolean'],
            'allowed_domains' => ['nullable', 'array'],
            'allowed_domains.*' => ['string', 'max:255'],
        ]);

        $channel = Channel::create([
            'type' => 'web_widget',
            'name' => $data['name'],
            'enabled' => $data['enabled'] ?? true,
            'config' => [],
            'settings' => $this->defaultSettings($data['name']),
            'allowed_domains' => $this->normalizeDomains($data['allowed_domains'] ?? []),
            'sort' => (Channel::where('type', 'web_widget')->max('sort') ?? 0) + 1,
        ]);

        return redirect()->route('chatbot.admin.widget.edit', $channel)
            ->with('success', 'Widget creado. Configura sus opciones y copia el snippet de embed.');
    }

    public function edit(Channel $webWidget): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);
        abort_unless($webWidget->type->value === 'web_widget', 404);

        $settings = $webWidget->settings ?? [];

        return Inertia::render('ChatBot::Widget/Edit', [
            'widget' => [
                'id' => $webWidget->id,
                'is_new' => false,
                'enabled' => $webWidget->enabled,
                'name' => $webWidget->name,
                'title' => $settings['title'] ?? 'Asistente virtual',
                'subtitle' => $settings['subtitle'] ?? null,
                'greeting' => $settings['greeting'] ?? null,
                'position' => $settings['position'] ?? 'right',
                'avatar_media_id' => $settings['avatar_media_id'] ?? null,
                'avatar_url' => null,
                'require_auth' => $settings['require_auth'] ?? true,
                'show_typing' => $settings['show_typing'] ?? true,
                'offline_message' => $settings['offline_message'] ?? null,
                'allowed_domains' => $webWidget->allowed_domains ?? [],
                'public_key' => $webWidget->public_key,
            ],
        ]);
    }

    public function update(UpdateWidgetRequest $request, Channel $webWidget): RedirectResponse
    {
        abort_unless($webWidget->type->value === 'web_widget', 404);

        $data = $request->validated();

        $settings = $webWidget->settings ?? [];
        foreach (['title', 'subtitle', 'greeting', 'position', 'avatar_media_id', 'require_auth', 'show_typing', 'offline_message'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = $data[$key];
            }
        }

        $webWidget->update([
            'name' => $data['name'] ?? $webWidget->name,
            'enabled' => $data['enabled'] ?? $webWidget->enabled,
            'settings' => $settings,
            'allowed_domains' => $this->normalizeDomains($data['allowed_domains'] ?? []),
        ]);

        return back()->with('success', 'Configuración del widget actualizada.');
    }

    public function destroy(Channel $webWidget): RedirectResponse
    {
        abort_unless(request()->user()?->can('view chatbot'), 403);
        abort_unless($webWidget->type->value === 'web_widget', 404);

        $webWidget->delete();

        return redirect()->route('chatbot.admin.widget')
            ->with('success', 'Widget eliminado.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function defaultSettings(string $name): array
    {
        return [
            'title' => $name,
            'subtitle' => 'Te respondemos en minutos',
            'greeting' => '¡Hola! ¿En qué podemos ayudarte?',
            'position' => 'right',
            'require_auth' => true,
            'show_typing' => true,
            'offline_message' => null,
        ];
    }

    /**
     * @param  array<int, string>|null  $domains
     * @return array<int, string>
     */
    private function normalizeDomains(?array $domains): array
    {
        if (empty($domains)) {
            return [];
        }

        $clean = [];
        foreach ($domains as $d) {
            $d = trim((string) $d);
            $d = preg_replace('#^https?://#i', '', $d);
            $d = rtrim($d, '/');
            if ($d !== '') {
                $clean[] = $d;
            }
        }

        return array_values(array_unique($clean));
    }
}
