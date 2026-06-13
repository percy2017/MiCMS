<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Channels\Evolution\EvolutionApiClient;
use Modules\ChatBot\Channels\OpenWa\OpenWaStatsProvider;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;

class ChannelAdminController extends Controller
{
    public function __construct(
        private readonly ChannelRegistry $registry,
    ) {}

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $driver = $this->registry->get('evolution');

        $channels = Channel::orderBy('sort')->get()->map(function (Channel $c) use ($driver): array {
            $base = [
                'id' => $c->id,
                'type' => $c->type->value,
                'name' => $c->settings['display_name'] ?? $c->name ?? $c->type->value,
                'enabled' => $c->enabled,
                'url' => match ($c->type->value) {
                    'evolution' => route('chatbot.admin.evolution.edit', $c),
                    'openwa' => route('chatbot.admin.openwa.edit', $c),
                    'web_widget' => route('chatbot.admin.widget.edit', $c),
                    default => '#',
                },
            ];

            if ($c->type->value === 'evolution') {
                $config = $c->config ?? [];
                $base['instance_name'] = $config['instance_name'] ?? null;
                $base['instance_id'] = $config['instance_id'] ?? null;
                $base['profile_name'] = $config['profile_name'] ?? null;
                $base['profile_picture_url'] = $config['profile_picture_url'] ?? null;
                $base['owner_jid'] = $config['owner_jid'] ?? null;

                $base['connection_status'] = 'unknown';
                try {
                    $stats = $driver?->stats($c) ?? [];
                    $base['connection_status'] = $stats['state'] ?? 'unknown';
                } catch (\Throwable) {
                    // keep 'unknown'
                }
            }

            if ($c->type->value === 'web_widget') {
                $base['widget_title'] = $c->settings['title'] ?? null;
                $base['public_key'] = $c->public_key;
                $base['allowed_domain'] = $c->allowed_domain;
                $base['conversations_count'] = $c->conversations()->count();
            }

            return $base;
        });

        return Inertia::render('ChatBot::Canales/Index', [
            'channels' => $channels,
        ]);
    }

    public function show(Channel $channel): RedirectResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        return match ($channel->type->value) {
            'evolution' => redirect()->route('chatbot.admin.evolution.edit', $channel),
            'web_widget' => redirect()->route('chatbot.admin.widget.edit', $channel),
            default => redirect()->route('chatbot.admin.canales'),
        };
    }

    public function storeEvolution(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('view chatbot'), 403);

        $channel = Channel::create([
            'type' => 'evolution',
            'name' => 'WhatsApp',
            'enabled' => false,
            'config' => [
                'server_url' => $request->input('server_url', env('EVOLUTION_DEFAULT_SERVER_URL', '')),
                'api_key' => $request->input('api_key', env('EVOLUTION_DEFAULT_API_KEY', '')),
                'instance_name' => '',
                'instance_id' => '',
                'profile_name' => '',
                'profile_picture_url' => '',
                'owner_jid' => '',
            ],
            'settings' => [
                'display_name' => 'WhatsApp',
                'auto_reply' => '',
            ],
        ]);

        return redirect()->route('chatbot.admin.canales')
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
            'webhookUrl' => route('webhooks.evolution', $evolution),
        ]);
    }

    public function updateEvolution(Request $request, Channel $evolution): RedirectResponse
    {
        abort_unless($request->user()?->can('view chatbot'), 403);
        abort_unless($evolution->type->value === 'evolution', 404);

        $data = $request->validate([
            'config.server_url' => ['required', 'string', 'url'],
            'config.api_key' => ['required', 'string'],
            'config.instance_name' => [
                'required', 'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($evolution): void {
                    $exists = Channel::where('type', 'evolution')
                        ->where('id', '!=', $evolution->id)
                        ->get()
                        ->contains(fn (Channel $c): bool => ($c->config['instance_name'] ?? null) === $value);
                    if ($exists) {
                        $fail('El nombre de instancia ya está en uso por otro canal.');
                    }
                },
            ],
            'config.instance_id' => ['nullable', 'string', 'max:255'],
            'config.profile_name' => ['nullable', 'string', 'max:255'],
            'config.profile_picture_url' => ['nullable', 'string', 'max:2048'],
            'config.owner_jid' => ['nullable', 'string', 'max:255'],
            'settings.display_name' => ['nullable', 'string', 'max:255'],
            'settings.auto_reply' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $evolution->update([
            'enabled' => $data['enabled'] ?? $evolution->enabled,
            'config' => $data['config'],
            'settings' => $data['settings'] ?? $evolution->settings,
        ]);

        // Auto-configure webhook on Evolution API
        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($data['config']['server_url'], '/'),
                apiKey: $data['config']['api_key'],
                instanceName: $data['config']['instance_name'],
            );

            $client->setWebhook([
                'url' => route('webhooks.evolution', $evolution),
                'webhook_by_events' => true,
                'webhook_base64' => true,
                'webhook_events' => [
                    'messages.upsert',
                    'messages.update',
                    'messages.reaction',
                    'send.message',
                    'connection.update',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Evolution setWebhook failed', [
                'channel_id' => $evolution->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('chatbot.admin.canales')
            ->with('success', 'Canal WhatsApp actualizado.');
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        abort_unless(request()->user()?->can('view chatbot'), 403);

        $mediaIds = $channel->conversations()
            ->with('messages')
            ->get()
            ->flatMap(fn ($conv) => $conv->messages->pluck('attachment_media_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $channel->conversations()->each(fn ($conv) => $conv->messages()->forceDelete());
        $channel->conversations()->forceDelete();

        $this->purgeMedia($mediaIds);

        if ($channel->type?->value === 'evolution' && ($config = $channel->config)) {
            try {
                $client = new EvolutionApiClient(
                    serverUrl: rtrim($config['server_url'] ?? '', '/'),
                    apiKey: $config['api_key'] ?? '',
                    instanceName: $config['instance_name'] ?? '',
                );
                $client->disconnectInstance();
            } catch (\Throwable $e) {
                Log::warning('Evolution disconnectInstance failed on channel delete', [
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $channel->forceDelete();

        return redirect()->route('chatbot.admin.canales')
            ->with('success', 'Canal eliminado permanentemente.');
    }

    private function purgeMedia(array $mediaIds): void
    {
        if (empty($mediaIds)) {
            return;
        }

        $medias = Media::whereIn('id', $mediaIds)->get();

        foreach ($medias as $media) {
            try {
                if ($media->path && Storage::disk($media->disk ?? 'public')->exists($media->path)) {
                    Storage::disk($media->disk ?? 'public')->delete($media->path);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete media file from disk', [
                    'media_id' => $media->id,
                    'path' => $media->path,
                    'error' => $e->getMessage(),
                ]);
            }

            $media->delete();
        }
    }

    public function fetchInstances(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view chatbot'), 403);

        $data = $request->validate([
            'server_url' => ['nullable', 'string', 'url'],
            'api_key' => ['nullable', 'string'],
            'exclude' => ['nullable', 'integer'],
        ]);

        $serverUrl = $data['server_url'] ?? (string) env('EVOLUTION_DEFAULT_SERVER_URL', '');
        $apiKey = $data['api_key'] ?? (string) env('EVOLUTION_DEFAULT_API_KEY', '');

        if ($serverUrl === '' || $apiKey === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Configura EVOLUTION_DEFAULT_SERVER_URL y EVOLUTION_DEFAULT_API_KEY en .env.',
            ], 422);
        }

        // Collect instance_names already assigned to other evolution channels
        $takenNames = Channel::where('type', 'evolution')
            ->where('id', '!=', $data['exclude'] ?? 0)
            ->get()
            ->pluck('config.instance_name')
            ->filter()
            ->values()
            ->toArray();

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($serverUrl, '/'),
                apiKey: $apiKey,
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
                if ($name && ! in_array($name, $takenNames, true)) {
                    $list[] = [
                        'name' => $name,
                        'instance_id' => $inst['id'] ?? $inst['instanceId'] ?? null,
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

    // ============== OpenWA ==============

    /**
     * Página selector: lista sesiones de OpenWA para crear un inbox.
     */
    public function openwaSelector(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        return Inertia::render('ChatBot::Canales/OpenWaSelector');
    }

    /**
     * Devuelve las sesiones disponibles en OpenWA.
     */
    public function openwaAvailableSessions(): JsonResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $provider = new OpenWaStatsProvider;

        return response()->json($provider->listAvailableSessions());
    }

    /**
     * Crea un Channel OpenWA vinculando la session_name seleccionada.
     * Redirige a la página de edición.
     */
    public function storeOpenWa(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $data = $request->validate([
            'session_name' => 'required|string|max:100',
        ]);

        $existing = null;
        foreach (Channel::where('type', ChannelType::OpenWa)->where('enabled', true)->get() as $ch) {
            if (is_array($ch->config ?? null) && ($ch->config['session_name'] ?? null) === $data['session_name']) {
                $existing = $ch;
                break;
            }
        }

        if ($existing) {
            return back()->withErrors(['session_name' => "La sesión '{$data['session_name']}' ya está vinculada al canal #{$existing->id}."]);
        }

        $channel = Channel::create([
            'type' => ChannelType::OpenWa,
            'name' => $data['session_name'],
            'enabled' => true,
            'config' => ['session_name' => $data['session_name']],
            'settings' => ['display_name' => $data['session_name']],
            'sort' => (Channel::max('sort') ?? 0) + 1,
        ]);

        return redirect()->route('chatbot.admin.openwa.edit', $channel)->with('success', "Inbox OpenWA '{$data['session_name']}' creado.");
    }

    /**
     * Página de edición de un canal OpenWa existente.
     */
    public function editOpenWa(Channel $openwa): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        if ($openwa->type->value !== 'openwa') {
            abort(404);
        }

        return Inertia::render('ChatBot::Canales/OpenWaEdit', [
            'channel' => [
                'id' => $openwa->id,
                'type' => $openwa->type->value,
                'name' => $openwa->name,
                'enabled' => $openwa->enabled,
                'config' => $openwa->config ?? [],
                'settings' => $openwa->settings ?? [],
            ],
        ]);
    }

    /**
     * Actualiza un canal OpenWa (session_name + display_name + auto_reply + enabled).
     */
    public function updateOpenWa(Request $request, Channel $openwa): RedirectResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        if ($openwa->type->value !== 'openwa') {
            abort(404);
        }

        $data = $request->validate([
            'enabled' => 'boolean',
            'config.session_name' => 'required|string|max:100',
            'settings.display_name' => 'nullable|string|max:100',
            'settings.auto_reply' => 'nullable|string',
        ]);

        $openwa->update([
            'enabled' => $data['enabled'] ?? false,
            'config' => ['session_name' => $data['config']['session_name']],
            'settings' => $data['settings'] ?? [],
        ]);

        return back()->with('success', 'Canal OpenWA actualizado.');
    }

    public function openwaStats(Channel $openwa): JsonResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        if ($openwa->type->value !== 'openwa') {
            abort(404);
        }

        $driver = $this->registry->get('openwa');
        if (! $driver) {
            return response()->json(['connected' => false, 'error' => 'Driver OpenWA no registrado'], 500);
        }

        return response()->json($driver->stats($openwa));
    }

    // ============== Evolution Selector ==============

    /**
     * Página selector: lista instancias de Evolution para crear un inbox.
     */
    public function evolutionSelector(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        return Inertia::render('ChatBot::Canales/EvolutionSelector');
    }

    /**
     * Crea un Channel Evolution a partir del instance_name seleccionado.
     * Redirige a la página de edición.
     */
    public function evolutionSelectStore(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $data = $request->validate([
            'instance_name' => 'required|string|max:100',
        ]);

        $existing = Channel::where('type', ChannelType::Evolution)
            ->where('config->instance_name', $data['instance_name'])
            ->where('enabled', true)
            ->first();

        if ($existing) {
            return back()->withErrors(['instance_name' => "La instancia '{$data['instance_name']}' ya está vinculada."]);
        }

        $channel = Channel::create([
            'type' => ChannelType::Evolution,
            'name' => $data['instance_name'],
            'enabled' => true,
            'config' => [
                'server_url' => env('EVOLUTION_DEFAULT_SERVER_URL', ''),
                'api_key' => env('EVOLUTION_DEFAULT_API_KEY', ''),
                'instance_name' => $data['instance_name'],
            ],
            'settings' => ['display_name' => $data['instance_name']],
            'sort' => (Channel::max('sort') ?? 0) + 1,
        ]);

        return redirect()->route('chatbot.admin.evolution.edit', $channel)->with('success', "Inbox Evolution '{$data['instance_name']}' creado.");
    }
}
