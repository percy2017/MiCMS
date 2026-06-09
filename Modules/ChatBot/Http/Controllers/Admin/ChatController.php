<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Http\Requests\ReplyMessageRequest;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Services\ChatBotMessageService;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatBotMessageService $service,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view chats'), 403);

        $conversations = Conversation::query()
            ->with(['user:id,name,email', 'assignedAdmin:id,name'])
            ->with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', (string) $request->input('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->input('search');
                $query->where(function ($q) use ($search): void {
                    $q->where('visitor_name', 'like', "%{$search}%")
                        ->orWhere('visitor_email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Conversation $c): array => [
                'id' => $c->id,
                'visitor_name' => $c->visitor_name,
                'visitor_email' => $c->visitor_email,
                'status' => $c->status,
                'unread_by_admin' => $c->unread_by_admin,
                'messages_count' => $c->messages()->count(),
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'last_message_at_diff' => $c->last_message_at?->diffForHumans(),
                'last_message_preview' => $c->messages->first()?->content,
                'user' => $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'email' => $c->user->email,
                ] : null,
            ]);

        $stats = [
            'open' => Conversation::where('status', 'open')->count(),
            'unread' => Conversation::where('unread_by_admin', '>', 0)->count(),
            'total' => Conversation::count(),
        ];

        $activeId = $request->integer('active') ?: null;
        $active = null;
        if ($activeId) {
            $conv = Conversation::with(['user', 'messages.attachment'])->find($activeId);
            if ($conv) {
                $active = [
                    'id' => $conv->id,
                    'visitor_name' => $conv->visitor_name,
                    'visitor_email' => $conv->visitor_email,
                    'page_url' => $conv->page_url,
                    'status' => $conv->status,
                    'user_id' => $conv->user_id,
                    'last_message_at' => $conv->last_message_at?->toIso8601String(),
                    'messages' => $conv->messages->map(fn (Message $m): array => [
                        'id' => $m->id,
                        'role' => $m->role,
                        'content' => $m->content,
                        'attachment_url' => $m->attachment?->url(),
                        'read_at' => $m->read_at?->toIso8601String(),
                        'created_at' => $m->created_at?->toIso8601String(),
                    ])->values()->all(),
                ];
            }
        }

        return Inertia::render('ChatBot::Chats/Index', [
            'conversations' => $conversations,
            'stats' => $stats,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => $request->input('status'),
            ],
            'active' => $active,
        ]);
    }

    public function show(Conversation $conversation)
    {
        abort_unless(request()->user()?->can('view chats'), 403);

        $conversation->load(['user', 'messages.attachment']);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'visitor_name' => $conversation->visitor_name,
                'visitor_email' => $conversation->visitor_email,
                'page_url' => $conversation->page_url,
                'status' => $conversation->status,
                'user_id' => $conversation->user_id,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'messages' => $conversation->messages->map(fn (Message $m): array => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'attachment_url' => $m->attachment?->url(),
                    'read_at' => $m->read_at?->toIso8601String(),
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }

    public function reply(ReplyMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($request->user()?->can('reply chatbot'), 403);

        $message = $this->service->sendAdminMessage(
            $conversation,
            $request->string('content')->toString(),
            $request->integer('attachment_media_id') ?: null,
        );

        $conversation->update([
            'assigned_to' => $conversation->assigned_to ?? $request->user()->id,
        ]);

        return back()->with('success', 'Respuesta enviada.');
    }

    public function read(Conversation $conversation, Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('view chats'), 403);

        $this->service->markAsRead($conversation, $request->user()->id);

        return back();
    }

    public function close(Conversation $conversation): RedirectResponse
    {
        abort_unless(request()->user()?->can('reply chatbot'), 403);

        $conversation->update(['status' => 'closed']);

        return back()->with('success', 'Conversación cerrada.');
    }

    public function reopen(Conversation $conversation): RedirectResponse
    {
        abort_unless(request()->user()?->can('reply chatbot'), 403);

        $conversation->update(['status' => 'open']);

        return back()->with('success', 'Conversación reabierta.');
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        abort_unless(request()->user()?->can('delete chatbot conversations'), 403);

        $conversation->delete();

        return to_route('chatbot.admin.chats')->with('success', 'Conversación eliminada.');
    }
}
