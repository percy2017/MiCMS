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

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $instance = $request->input('instance');

        $channel = Channel::where('type', 'evolution')
            ->where('enabled', true)
            ->first();

        if (! $channel) {
            return response()->json(['ok' => false, 'error' => 'No active evolution channel'], 404);
        }

        $this->ingestor->ingest($channel, $payload);

        return response()->json(['ok' => true]);
    }
}
