<?php

namespace Modules\ChatBot\Http\Controllers\Api\OpenWa;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\OpenWa\OpenWaMessageParser;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Services\MessageIngestor;
use Modules\ChatBot\Services\OpenWa\OpenWaHmacVerifier;

class OpenWaWebhookController extends Controller
{
    public function __construct(
        private readonly MessageIngestor $ingestor,
        private readonly OpenWaHmacVerifier $verifier,
    ) {}

    public function handle(Request $request, Channel $channel): JsonResponse
    {
        $secret = (string) config('chatbot.openwa.webhook_secret', '');

        if (! $this->verifier->verify($request, $secret)) {
            Log::warning('OpenWaWebhook: firma HMAC inválida', [
                'channel_id' => $channel->id,
                'header' => $request->header('X-OpenWA-Signature'),
            ]);

            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        if (! $channel->enabled || $channel->type->value !== 'openwa') {
            return response()->json(['ok' => false, 'error' => 'channel_not_available'], 404);
        }

        $idempotencyKey = $request->header('X-OpenWA-Idempotency-Key');
        $event = $request->input('event');

        Log::warning('OpenWaWebhook: recibido', [
            'channel_id' => $channel->id,
            'event' => $event,
            'idempotency_key' => $idempotencyKey,
            'retry_count' => $request->header('X-OpenWA-Retry-Count', 0),
        ]);

        if ($idempotencyKey) {
            $cacheKey = "openwa:webhook:{$channel->id}:{$idempotencyKey}";
            if (Cache::has($cacheKey)) {
                return response()->json(['ok' => true, 'status' => 'duplicate_ignored']);
            }
            Cache::put($cacheKey, true, now()->addDay());
        }

        if ($event === 'message.revoked') {
            return $this->handleRevoke($channel, $request->input('data', []));
        }

        if (in_array($event, ['session.status', 'session.qr', 'session.authenticated', 'session.disconnected'], true)) {
            return $this->handleSessionEvent($channel, $event, $request->input('data', []));
        }

        if (! OpenWaMessageParser::isMessageEvent($request->all())) {
            return response()->json(['ok' => true, 'status' => 'ignored_non_message_event']);
        }

        try {
            $message = $this->ingestor->ingest($channel, $request->all());

            Log::warning('OpenWaWebhook: ingest completado', [
                'channel_id' => $channel->id,
                'event' => $event,
                'message_id_saved' => $message?->id,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('OpenWaWebhook: excepción no manejada', [
                'channel_id' => $channel->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'exception'], 500);
        }
    }

    private function handleRevoke(Channel $channel, array $data): JsonResponse
    {
        $messageId = $data['messageId'] ?? null;
        if (! $messageId) {
            return response()->json(['ok' => true, 'status' => 'revoke_no_message_id']);
        }

        $deleted = Message::where('external_id', $messageId)
            ->whereHas('conversation', fn ($q) => $q->where('channel_id', $channel->id))
            ->forceDelete();

        return response()->json(['ok' => true, 'status' => 'revoke_processed', 'rows_deleted' => $deleted]);
    }

    private function handleSessionEvent(Channel $channel, string $event, array $data): JsonResponse
    {
        Log::warning('OpenWaWebhook: evento de sesión', [
            'channel_id' => $channel->id,
            'event' => $event,
            'status' => $data['status'] ?? null,
        ]);

        return response()->json(['ok' => true, 'status' => 'session_event_logged']);
    }
}
