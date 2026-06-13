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

    public function widget(Request $request): JsonResponse
    {
        $publicKey = $request->string('key')->toString();

        if ($publicKey === '') {
            return response()->json(['enabled' => false, 'reason' => 'missing_key'], 400);
        }

        $channel = Channel::where('type', 'web_widget')
            ->where('public_key', $publicKey)
            ->first();

        if (! $channel) {
            return response()->json(['enabled' => false, 'reason' => 'invalid_key'], 404);
        }

        if (! $channel->enabled) {
            return response()->json(['enabled' => false, 'reason' => 'disabled']);
        }

        if (! $this->originAllowed($request, $channel->allowed_domain)) {
            return response()->json(['enabled' => false, 'reason' => 'domain_not_allowed'], 403);
        }

        $settings = $channel->settings ?? [];

        return response()->json([
            'enabled' => true,
            'key' => $channel->public_key,
            'name' => $channel->name,
            'title' => $settings['title'] ?? 'Asistente virtual',
            'subtitle' => $settings['subtitle'] ?? 'Te respondemos en minutos',
            'greeting' => $settings['greeting'] ?? '¡Hola! ¿En qué podemos ayudarte?',
            'position' => $settings['position'] ?? 'right',
            'require_auth' => $settings['require_auth'] ?? false,
            'show_typing' => $settings['show_typing'] ?? true,
            'offline_message' => $settings['offline_message'] ?? null,
            'avatar_url' => null,
        ]);
    }

    private function originAllowed(Request $request, ?string $allowedDomain): bool
    {
        if (empty($allowedDomain)) {
            return false;
        }

        $origin = $this->extractHost($request->header('Origin') ?? $request->header('Referer') ?? '');

        if ($origin === '') {
            return true;
        }

        $allowedDomain = strtolower(trim($allowedDomain));
        $origin = strtolower($origin);

        if (str_starts_with($allowedDomain, '*.')) {
            $suffix = substr($allowedDomain, 1);

            return str_ends_with($origin, $suffix);
        }

        return $origin === $allowedDomain;
    }

    private function extractHost(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return preg_replace('#^https?://#i', '', $url) ?? '';
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
