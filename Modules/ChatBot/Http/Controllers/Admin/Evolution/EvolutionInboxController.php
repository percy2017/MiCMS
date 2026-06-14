<?php

namespace Modules\ChatBot\Http\Controllers\Admin\Evolution;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Channels\Evolution\EvolutionApiClient;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Inbox\Shared\ChannelInboxService;
use Modules\ChatBot\Inbox\Shared\InboxStrategyRegistry;
use Modules\ChatBot\Models\Channel;

class EvolutionInboxController extends Controller
{
    public function __construct(
        private readonly InboxStrategyRegistry $registry,
        private readonly ChannelInboxService $service,
    ) {}

    /**
     * One page that shows the list of Evolution instances + a form to save.
     * Selecting an instance from the list fills the form. "Guardar" creates
     * the channel and redirects to the index.
     */
    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('view chatbot'), 403);

        $strategy = $this->registry->get(ChannelType::Evolution);
        $available = $strategy->listAvailable();

        // Resolve the webhook integration + full instance info for the
        // requested instance (if any). Everything is read from the
        // Evolution API on the server side, never hardcoded.
        $requested = $request->query('instance_name');
        $integration = $requested ? $strategy->integrationInfo((string) $requested) : null;
        $instance = $requested ? $strategy->findExternalItemFull((string) $requested) : null;

        return Inertia::render('ChatBot::Canales/EvolutionCreate', [
            'available' => $available,
            'selectedInstance' => $requested,
            'instance' => $instance,
            'integration' => $integration,
        ]);
    }

    /**
     * JSON for the "Refrescar" button on the page.
     */
    public function fetchInstances(): JsonResponse
    {
        abort_unless(request()->user()?->can('view chatbot'), 403);

        $strategy = $this->registry->get(ChannelType::Evolution);

        return response()->json($strategy->listAvailable());
    }

    /**
     * Persist the form. Redirects back to the index.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('view chatbot'), 403);

        $data = $request->validate([
            'config.instance_name' => [
                'required', 'string', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->service->findExisting(ChannelType::Evolution, (string) $value)) {
                        $fail('El nombre de instancia ya está en uso por otro canal.');
                    }
                },
            ],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $instanceName = (string) $data['config']['instance_name'];

        // Pull server_url + api_key from .env; profile info from the external API.
        $strategy = $this->registry->get(ChannelType::Evolution);
        $external = $strategy->findExternalItem($instanceName) ?? [];
        $serverUrl = (string) env('EVOLUTION_DEFAULT_SERVER_URL', '');
        $apiKey = (string) env('EVOLUTION_DEFAULT_API_KEY', '');

        $channel = Channel::create([
            'type' => ChannelType::Evolution,
            'name' => $instanceName,
            'enabled' => $data['enabled'] ?? true,
            'config' => [
                'server_url' => $serverUrl,
                'api_key' => $apiKey,
                'instance_name' => $instanceName,
                'instance_id' => (string) ($external['external_id'] ?? ''),
                'profile_name' => (string) ($external['profile_name'] ?? ''),
                'profile_picture_url' => (string) ($external['profile_picture_url'] ?? ''),
                'owner_jid' => (string) ($external['owner'] ?? ''),
            ],
            'sort' => (Channel::max('sort') ?? 0) + 1,
        ]);

        $strategy->onInboxCreated($channel);

        return redirect()
            ->route('chatbot.admin.canales')
            ->with('success', "Inbox Evolution '{$channel->name}' creado.");
    }

    /**
     * Edit an existing Evolution inbox. The instance is fixed (read-only).
     * Reads the live settings from Evolution API to prefill the form.
     */
    public function edit(Request $request, Channel $evolution): Response
    {
        abort_unless($request->user()?->can('view chatbot'), 403);
        abort_unless($evolution->type === ChannelType::Evolution, 404);

        $strategy = $this->registry->get(ChannelType::Evolution);
        $instanceName = (string) ($evolution->config['instance_name'] ?? $evolution->name);

        $liveSettings = $strategy->currentSettings($instanceName);
        $liveWebhook = $strategy->currentWebhook($instanceName);

        return Inertia::render('ChatBot::Canales/EvolutionEdit', [
            'channel' => [
                'id' => $evolution->id,
                'name' => $evolution->name,
                'enabled' => $evolution->enabled,
                'instance_name' => $instanceName,
                'profile_name' => (string) ($evolution->config['profile_name'] ?? ''),
                'profile_picture_url' => (string) ($evolution->config['profile_picture_url'] ?? ''),
                'owner_jid' => (string) ($evolution->config['owner_jid'] ?? ''),
            ],
            'liveSettings' => $liveSettings,
            'liveWebhook' => $liveWebhook,
        ]);
    }

    /**
     * Update an existing Evolution inbox. Persists `enabled` to DB and
     * pushes `groupsIgnore` (and any other settings keys) to Evolution API.
     */
    public function update(Request $request, Channel $evolution): RedirectResponse
    {
        abort_unless($request->user()?->can('view chatbot'), 403);
        abort_unless($evolution->type === ChannelType::Evolution, 404);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'groups_ignore' => ['sometimes', 'boolean'],
            'reject_call' => ['sometimes', 'boolean'],
            'always_online' => ['sometimes', 'boolean'],
            'read_messages' => ['sometimes', 'boolean'],
            'read_status' => ['sometimes', 'boolean'],
            'sync_full_history' => ['sometimes', 'boolean'],
            'msg_call' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $config = $evolution->config;
        $serverUrl = rtrim((string) ($config['server_url'] ?? ''), '/');
        $apiKey = (string) ($config['api_key'] ?? '');
        $instanceName = (string) ($config['instance_name'] ?? $evolution->name);

        $evolution->enabled = $data['enabled'] ?? $evolution->enabled;
        $evolution->save();

        $hasSettingsPatch = collect([
            'groups_ignore', 'reject_call', 'always_online', 'read_messages',
            'read_status', 'sync_full_history', 'msg_call',
        ])->contains(fn (string $k): bool => array_key_exists($k, $data));

        if ($serverUrl !== '' && $apiKey !== '' && $instanceName !== '' && $hasSettingsPatch) {
            try {
                $client = new EvolutionApiClient(
                    serverUrl: $serverUrl,
                    apiKey: $apiKey,
                    instanceName: $instanceName,
                );
                $patch = [];
                if (array_key_exists('groups_ignore', $data)) {
                    $patch['groupsIgnore'] = (bool) $data['groups_ignore'];
                }
                if (array_key_exists('reject_call', $data)) {
                    $patch['rejectCall'] = (bool) $data['reject_call'];
                }
                if (array_key_exists('always_online', $data)) {
                    $patch['alwaysOnline'] = (bool) $data['always_online'];
                }
                if (array_key_exists('read_messages', $data)) {
                    $patch['readMessages'] = (bool) $data['read_messages'];
                }
                if (array_key_exists('read_status', $data)) {
                    $patch['readStatus'] = (bool) $data['read_status'];
                }
                if (array_key_exists('sync_full_history', $data)) {
                    $patch['syncFullHistory'] = (bool) $data['sync_full_history'];
                }
                if (array_key_exists('msg_call', $data)) {
                    $patch['msgCall'] = (string) $data['msg_call'];
                }
                $response = $client->setSettings($patch);
                if (! $response->successful()) {
                    return redirect()
                        ->route('chatbot.admin.canales')
                        ->with('error', "Evolution rechazó los cambios (HTTP {$response->status()}).");
                }
            } catch (\Throwable $e) {
                Log::warning('Evolution setSettings failed on channel update', [
                    'channel_id' => $evolution->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()
                    ->route('chatbot.admin.canales')
                    ->with('error', "No se pudo actualizar la instancia en Evolution: {$e->getMessage()}");
            }
        }

        return redirect()
            ->route('chatbot.admin.canales')
            ->with('success', "Inbox '{$evolution->name}' actualizado.");
    }
}
