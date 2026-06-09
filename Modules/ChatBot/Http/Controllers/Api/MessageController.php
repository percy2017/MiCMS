<?php

namespace Modules\ChatBot\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\ChatBot\Events\ChatBotTyping;
use Modules\ChatBot\Http\Requests\StoreMessageRequest;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Services\ChatBotMessageService;

class MessageController extends Controller
{
    public function __construct(
        private readonly ChatBotMessageService $service,
    ) {}

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        if ($conversation->status->value === 'closed') {
            return response()->json(['error' => 'conversation_closed'], 409);
        }

        $message = $this->service->sendUserMessage(
            $conversation,
            $request->string('content')->toString(),
            $request->integer('attachment_media_id') ?: null,
        );

        return response()->json([
            'message' => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function typing(Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== request()->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        ChatBotTyping::dispatch(
            $conversation->id,
            $conversation->visitor_name,
        );

        return response()->json(['ok' => true]);
    }
}
