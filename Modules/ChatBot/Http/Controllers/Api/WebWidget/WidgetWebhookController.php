<?php

namespace Modules\ChatBot\Http\Controllers\Api\WebWidget;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ChatBot\Events\ChatBotMessageReceived;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Services\ChatBotMessageService;

class WidgetWebhookController extends Controller
{
    public function __construct(
        private readonly ChatBotMessageService $service,
    ) {}

    /**
     * Webhook entrante desde un widget embebido.
     *
     * El widget del navegador hace POST a /api/webhooks/widget/{channel}/{token}
     * cada vez que el visitante envía un mensaje.
     */
    public function handle(Request $request, Channel $channel, string $token): JsonResponse
    {
        if ($channel->type->value !== 'web_widget') {
            return response()->json(['error' => 'invalid_channel'], 404);
        }

        if (! $channel->webhook_token || ! hash_equals((string) $channel->webhook_token, $token)) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        if (! $channel->enabled) {
            return response()->json(['error' => 'channel_disabled'], 403);
        }

        if (! $this->originMatches($request, $channel->allowed_domain)) {
            return response()->json(['error' => 'origin_not_allowed'], 403);
        }

        $data = $request->validate([
            'session_id' => ['nullable', 'string', 'max:128'],
            'visitor' => ['required', 'array'],
            'visitor.name' => ['required', 'string', 'max:255'],
            'visitor.email' => ['required', 'email', 'max:255'],
            'visitor.phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'array'],
            'message.content' => ['required', 'string', 'max:5000'],
            'message.attachment_media_id' => ['nullable', 'integer'],
        ]);

        $user = $this->service->findOrCreateUser(
            email: $data['visitor']['email'],
            name: $data['visitor']['name'],
            phone: $data['visitor']['phone'] ?? null,
        );

        $conversation = Conversation::firstOrCreate(
            [
                'channel_id' => $channel->id,
                'user_id' => $user->id,
            ],
            [
                'visitor_name' => $data['visitor']['name'],
                'visitor_email' => $data['visitor']['email'],
                'status' => 'open',
                'last_message_at' => now(),
                'unread_by_admin' => 0,
            ],
        );

        $message = $this->service->sendUserMessage(
            $conversation,
            $data['message']['content'],
            $data['message']['attachment_media_id'] ?? null,
        );

        ChatBotMessageReceived::dispatch($message);

        return response()->json([
            'ok' => true,
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    private function originMatches(Request $request, ?string $allowedDomain): bool
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
}
