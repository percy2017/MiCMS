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

        $driver = $this->registry->get('evolution');

        $channels = Channel::orderBy('sort')->get()->map(function (Channel $c) use ($driver): array {
            $base = [
                'id' => $c->id,
                'type' => $c->type->value,
                'name' => $c->settings['display_name'] ?? $c->name ?? $c->type->value,
                'enabled' => $c->enabled,
                'url' => match ($c->type->value) {
                    'evolution' => route('chatbot.admin.evolution.edit', $c),
                    'web_widget' => route('chatbot.admin.widget'),
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
        abort_unless($request->user()?->can('update chatbot widget'), 403);
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

    public function destroy(Channel $evolution): RedirectResponse
    {
        abort_unless(request()->user()?->can('delete chatbot conversations'), 403);

        $mediaIds = $evolution->conversations()
            ->with('messages')
            ->get()
            ->flatMap(fn ($conv) => $conv->messages->pluck('attachment_media_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $evolution->conversations()->each(fn ($conv) => $conv->messages()->forceDelete());
        $evolution->conversations()->forceDelete();

        $this->purgeMedia($mediaIds);

        if ($evolution->type?->value === 'evolution' && ($config = $evolution->config)) {
            try {
                $client = new EvolutionApiClient(
                    serverUrl: rtrim($config['server_url'] ?? '', '/'),
                    apiKey: $config['api_key'] ?? '',
                    instanceName: $config['instance_name'] ?? '',
                );
                $client->disconnectInstance();
            } catch (\Throwable $e) {
                Log::warning('Evolution disconnectInstance failed on channel delete', [
                    'channel_id' => $evolution->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $evolution->forceDelete();

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
        abort_unless($request->user()?->can('update chatbot widget'), 403);

        $data = $request->validate([
            'server_url' => ['required', 'string', 'url'],
            'api_key' => ['required', 'string'],
            'exclude' => ['nullable', 'integer', 'exists:channels,id'],
        ]);

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
}
