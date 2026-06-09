<?php

namespace Modules\ChatBot\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ChatBot\Http\Requests\StartSessionRequest;
use Modules\ChatBot\Models\ChatBotConversation;
use Modules\ChatBot\Models\ChatBotMessage;
use Modules\ChatBot\Models\ChatBotWidget;
use Modules\ChatBot\Services\ChatBotAuthService;

class SessionController extends Controller
{
    public function __construct(
        private readonly ChatBotAuthService $auth,
    ) {}

    public function widget(): JsonResponse
    {
        $widget = ChatBotWidget::current();
        $widget->loadMissing('avatar');

        return response()->json([
            'enabled' => $widget->enabled,
            'title' => $widget->title,
            'subtitle' => $widget->subtitle,
            'greeting' => $widget->greeting,
            'position' => $widget->position,
            'require_auth' => $widget->require_auth,
            'show_typing' => $widget->show_typing,
            'offline_message' => $widget->offline_message,
            'avatar_url' => $widget->avatar?->url(),
        ]);
    }

    public function start(StartSessionRequest $request): JsonResponse
    {
        $result = $this->auth->startSession(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString() ?: null,
            name: $request->string('name')->toString() ?: null,
            action: $request->string('action')->toString(),
            pageUrl: $request->string('page_url')->toString() ?: null,
        );

        $conversation = $result['conversation']->load('messages');

        return response()->json([
            'is_new' => $result['is_new'],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
            'conversation' => $this->presentConversation($conversation),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['authenticated' => false], 401);
        }

        $conversation = ChatBotConversation::query()
            ->where('user_id', $user->id)
            ->where('status', ChatBotConversation::STATUS_OPEN)
            ->latest('last_message_at')
            ->first();

        if (! $conversation) {
            return response()->json(['authenticated' => true, 'conversation' => null]);
        }

        $conversation->load('messages');

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'conversation' => $this->presentConversation($conversation),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConversation(ChatBotConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'messages' => $conversation->messages->map(fn (ChatBotMessage $m): array => [
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
