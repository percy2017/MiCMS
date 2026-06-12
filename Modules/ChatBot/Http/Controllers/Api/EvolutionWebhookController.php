<?php

namespace Modules\ChatBot\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Services\MessageIngestor;

class EvolutionWebhookController extends Controller
{
    public function __construct(
        private readonly MessageIngestor $ingestor,
    ) {}

    public function handle(Request $request, Channel $channel): JsonResponse
    {
        if (! $channel->enabled || $channel->type->value !== 'evolution') {
            return response()->json(['ok' => false, 'error' => 'Channel not available'], 404);
        }

        $this->ingestor->ingest($channel, $request->all());

        return response()->json(['ok' => true]);
    }
}
