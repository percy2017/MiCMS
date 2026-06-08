<?php

namespace App\Http\Controllers\Package;

use App\Events\ChatMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\ChatWidgetMessage;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageMessageController extends Controller
{
    public function index(Request $request, Package $package): Response
    {
        $this->authorize('view', $package);

        if ($package->slug !== 'chat-widget') {
            abort(404);
        }

        $sessions = ChatWidgetMessage::query()
            ->select('session_id')
            ->selectRaw('MAX(created_at) as last_at')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('session_id')
            ->orderByDesc('last_at')
            ->get()
            ->map(function (object $row): array {
                $last = ChatWidgetMessage::query()
                    ->where('session_id', $row->session_id)
                    ->latest('id')
                    ->first();

                return [
                    'session_id' => $row->session_id,
                    'count' => (int) $row->count,
                    'last_at' => $last?->created_at?->toIso8601String(),
                    'last_human' => $last?->created_at?->diffForHumans(),
                    'name' => $last?->name,
                    'email' => $last?->email,
                    'preview' => $last ? mb_substr($last->message, 0, 60) : '',
                ];
            })
            ->all();

        return Inertia::render('admin/paquetes/messages', [
            'package' => $package->present(),
            'sessions' => $sessions,
            'messages' => [],
            'activeSessionId' => null,
        ]);
    }

    public function show(Request $request, Package $package, string $sessionId): Response
    {
        $this->authorize('view', $package);

        if ($package->slug !== 'chat-widget') {
            abort(404);
        }

        $sessions = $this->listSessions();
        $messages = ChatWidgetMessage::query()
            ->where('session_id', $sessionId)
            ->orderBy('id')
            ->get()
            ->map(fn (ChatWidgetMessage $m): array => $m->present())
            ->all();

        return Inertia::render('admin/paquetes/messages', [
            'package' => $package->present(),
            'sessions' => $sessions,
            'messages' => $messages,
            'activeSessionId' => $sessionId,
        ]);
    }

    public function store(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        if ($package->slug !== 'chat-widget') {
            abort(404);
        }

        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = ChatWidgetMessage::create([
            'session_id' => $data['session_id'],
            'message' => $data['message'],
            'direction' => ChatWidgetMessage::DIRECTION_OUTGOING,
        ]);

        broadcast(new ChatMessageReceived($message))->toOthers();

        return back();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listSessions(): array
    {
        return ChatWidgetMessage::query()
            ->select('session_id')
            ->selectRaw('MAX(created_at) as last_at')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('session_id')
            ->orderByDesc('last_at')
            ->get()
            ->map(function (object $row): array {
                $last = ChatWidgetMessage::query()
                    ->where('session_id', $row->session_id)
                    ->latest('id')
                    ->first();

                return [
                    'session_id' => $row->session_id,
                    'count' => (int) $row->count,
                    'last_at' => $last?->created_at?->toIso8601String(),
                    'last_human' => $last?->created_at?->diffForHumans(),
                    'name' => $last?->name,
                    'email' => $last?->email,
                    'preview' => $last ? mb_substr($last->message, 0, 60) : '',
                ];
            })
            ->all();
    }
}
