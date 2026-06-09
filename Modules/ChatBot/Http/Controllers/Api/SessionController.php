<?php

namespace Modules\ChatBot\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ChatBot\Http\Requests\StartSessionRequest;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Services\ChatBotAuthService;

class SessionController extends Controller
{
    public function __construct(
        private readonly ChatBotAuthService $auth,
    ) {}

    public function widget(): JsonResponse
    {
        $channel = Channel::where('type', 'web_widget')->first();

        if (! $channel) {
            return response()->json(['enabled' => false]);
        }

        $settings = $channel->settings ?? [];

        return response()->json([
            'enabled' => $channel->enabled,
            'title' => $settings['title'] ?? 'Asistente virtual',
            'subtitle' => $settings['subtitle'] ?? 'Te respondemos en minutos',
            'greeting' => $settings['greeting'] ?? '¡Hola! ¿En qué podemos ayudarte?',
            'position' => $settings['position'] ?? 'right',
            'require_auth' => $settings['require_auth'] ?? true,
            'show_typing' => $settings['show_typing'] ?? true,
            'offline_message' => $settings['offline_message'] ?? null,
            'avatar_url' => null,
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
            'authenticated' => true,
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

        $result = $this->auth->resumeSession($user);
        $conversation = $result['conversation']->load('messages');

        return response()->json([
            'authenticated' => true,
            'is_new' => $result['is_new'],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
            'conversation' => $this->presentConversation($conversation),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConversation(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'channel_id' => $conversation->channel_id,
            'status' => $conversation->status,
            'messages' => $conversation->messages->map(fn (Message $m): array => [
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
