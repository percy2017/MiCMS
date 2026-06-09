<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Channels\EvolutionApiClient;
use Modules\ChatBot\Models\Channel;

class ChannelAdminController extends Controller
{
    public function __construct(
        private readonly ChannelRegistry $registry,
    ) {}

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $channels = Channel::orderBy('sort')->get()->map(fn (Channel $c): array => [
            'id' => $c->id,
            'type' => $c->type->value,
            'name' => $c->name ?? $c->type->value,
            'enabled' => $c->enabled,
            'url' => match ($c->type->value) {
                'evolution' => route('chatbot.admin.evolution.edit', $c),
                'web_widget' => route('chatbot.admin.widget'),
                default => '#',
            },
        ]);

        return Inertia::render('ChatBot::Canales/Index', [
            'channels' => $channels,
        ]);
    }

    public function show(Channel $channel): RedirectResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        return match ($channel->type->value) {
            'evolution' => redirect()->route('chatbot.admin.evolution.edit', $channel),
            'web_widget' => redirect()->route('chatbot.admin.widget'),
            default => redirect()->route('chatbot.admin.canales'),
        };
    }

    public function storeEvolution(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('update chatbot widget'), 403);

        $channel = Channel::create([
            'type' => 'evolution',
            'name' => 'WhatsApp',
            'enabled' => false,
            'config' => [
                'server_url' => $request->input('server_url', env('EVOLUTION_DEFAULT_SERVER_URL', '')),
                'api_key' => $request->input('api_key', env('EVOLUTION_DEFAULT_API_KEY', '')),
                'instance_name' => '',
            ],
            'settings' => [
                'display_name' => 'WhatsApp',
                'auto_reply' => '',
            ],
        ]);

        return redirect()->route('chatbot.admin.evolution.edit', $channel)
            ->with('success', 'Canal WhatsApp creado.');
    }

    public function editEvolution(Channel $evolution): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);
        abort_unless($evolution->type->value === 'evolution', 404);

        $driver = $this->registry->get('evolution');
        $stats = $driver?->stats($evolution) ?? [];

        return Inertia::render('ChatBot::Canales/Evolution', [
            'channel' => [
                'id' => $evolution->id,
                'type' => $evolution->type->value,
                'name' => $evolution->name,
                'enabled' => $evolution->enabled,
                'config' => $evolution->config ?? [],
                'settings' => $evolution->settings ?? [],
            ],
            'stats' => $stats,
            'webhookUrl' => route('webhooks.evolution'),
        ]);
    }

    public function updateEvolution(Request $request, Channel $evolution): RedirectResponse
    {
        abort_unless($request->user()?->can('update chatbot widget'), 403);
        abort_unless($evolution->type->value === 'evolution', 404);

        $data = $request->validate([
            'config.server_url' => ['required', 'string', 'url'],
            'config.api_key' => ['required', 'string'],
            'config.instance_name' => ['required', 'string'],
            'settings.display_name' => ['nullable', 'string', 'max:255'],
            'settings.auto_reply' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $evolution->update([
            'enabled' => $data['enabled'] ?? $evolution->enabled,
            'config' => $data['config'],
            'settings' => $data['settings'] ?? $evolution->settings,
        ]);

        return back()->with('success', 'Canal WhatsApp actualizado.');
    }

    public function fetchInstances(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('update chatbot widget'), 403);

        $data = $request->validate([
            'server_url' => ['required', 'string', 'url'],
            'api_key' => ['required', 'string'],
        ]);

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($data['server_url'], '/'),
                apiKey: $data['api_key'],
                instanceName: '',
            );

            $response = $client->fetchInstances();

            if (! $response->successful()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Error Evolution API: HTTP '.$response->status(),
                ], 422);
            }

            $instances = $response->json();

            if (! is_array($instances)) {
                $instances = [];
            }

            $list = [];
            foreach ($instances as $inst) {
                $name = $inst['name'] ?? null;
                if ($name) {
                    $list[] = [
                        'name' => $name,
                        'status' => $inst['connectionStatus'] ?? 'unknown',
                        'owner' => $inst['ownerJid'] ?? null,
                        'profileName' => $inst['profileName'] ?? null,
                        'profilePictureUrl' => $inst['profilePicUrl'] ?? null,
                    ];
                }
            }

            return response()->json(['ok' => true, 'instances' => $list]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
