<?php

namespace Modules\ChatBot\Http\Controllers\Admin\OpenWa;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Inbox\Shared\ChannelInboxService;
use Modules\ChatBot\Inbox\Shared\InboxStrategyRegistry;
use Modules\ChatBot\Models\Channel;

class OpenWaInboxController extends Controller
{
    public function __construct(
        private readonly InboxStrategyRegistry $registry,
        private readonly ChannelInboxService $service,
    ) {}

    /**
     * One page that shows the list of OpenWA sessions + a form to save.
     * Selecting a session from the list fills the form. "Guardar" creates
     * the channel and redirects to the index.
     */
    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('view chatbot'), 403);

        $strategy = $this->registry->get(ChannelType::OpenWa);

        return Inertia::render('ChatBot::Canales/OpenWaCreate', [
            'available' => $strategy->listAvailable(),
        ]);
    }

    /**
     * JSON for the "Refrescar" button.
     */
    public function fetchAvailable(): JsonResponse
    {
        abort_unless(request()->user()?->can('view chatbot'), 403);

        $strategy = $this->registry->get(ChannelType::OpenWa);

        return response()->json($strategy->listAvailable());
    }

    /**
     * Persist the form. Redirects back to the index.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('view chatbot'), 403);

        $data = $request->validate([
            'config.session_name' => [
                'required', 'string', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->service->findExisting(ChannelType::OpenWa, (string) $value)) {
                        $fail('El nombre de sesión ya está en uso por otro canal.');
                    }
                },
            ],
            'settings.display_name' => ['nullable', 'string', 'max:255'],
            'settings.auto_reply' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $channel = Channel::create([
            'type' => ChannelType::OpenWa,
            'name' => $data['config']['session_name'],
            'enabled' => $data['enabled'] ?? true,
            'config' => $data['config'],
            'settings' => $data['settings'] ?? [],
            'sort' => (Channel::max('sort') ?? 0) + 1,
        ]);

        return redirect()
            ->route('chatbot.admin.canales')
            ->with('success', "Inbox OpenWA '{$channel->name}' creado.");
    }

    /**
     * Live status of an OpenWA session.
     */
    public function stats(Channel $openwa): JsonResponse
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);
        abort_unless($openwa->type === ChannelType::OpenWa, 404);

        $driver = app(\Modules\ChatBot\Channels\ChannelRegistry::class)->get('openwa');
        if (! $driver) {
            return response()->json(['connected' => false, 'error' => 'Driver OpenWA no registrado'], 500);
        }

        return response()->json($driver->stats($openwa));
    }
}
