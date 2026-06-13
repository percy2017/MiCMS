<?php

namespace Modules\ChatBot\Http\Controllers\Api\Evolution;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Services\MessageIngestor;

class EvolutionWebhookController extends Controller
{
    public function __construct(
        private readonly MessageIngestor $ingestor,
    ) {}

    public function handle(Request $request, Channel $channel): JsonResponse
    {
        $event = $request->input('event');
        $remoteJid = $request->input('data.key.remoteJid', $request->input('data.remoteJid'));
        $fromMe = $request->input('data.key.fromMe', $request->input('data.fromMe'));
        $messageId = $request->input('data.key.id', $request->input('data.id'));
        $instance = $request->input('instance');

        Log::warning('EvolutionWebhook: recibido', [
            'channel_id' => $channel->id,
            'event' => $event,
            'instance' => $instance,
            'remoteJid' => $remoteJid,
            'fromMe' => $fromMe,
            'message_id' => $messageId,
        ]);

        if (! $channel->enabled || $channel->type->value !== 'evolution') {
            Log::warning('EvolutionWebhook: canal no disponible', [
                'channel_id' => $channel->id,
                'enabled' => $channel->enabled,
                'type' => $channel->type->value,
            ]);

            return response()->json(['ok' => false, 'error' => 'Channel not available'], 404);
        }

        try {
            $message = $this->ingestor->ingest($channel, $request->all());

            Log::warning('EvolutionWebhook: ingest completado', [
                'channel_id' => $channel->id,
                'event' => $event,
                'remoteJid' => $remoteJid,
                'message_id_saved' => $message?->id,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('EvolutionWebhook: excepción no manejada', [
                'channel_id' => $channel->id,
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false, 'error' => 'exception'], 500);
        }
    }
}
