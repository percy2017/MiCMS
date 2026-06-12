<?php

namespace App\Http\Controllers\ChatBot;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;
use Modules\ChatBot\Services\ReactionBroadcaster;

class MessageReactionController extends Controller
{
    public function __construct(private readonly ReactionBroadcaster $broadcaster) {}

    public function store(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($request->user()?->can('reply chatbot'), 403);

        if ($message->conversation_id !== $conversation->id) {
            abort(404);
        }

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $userJid = $this->resolveAdminJid($request);

        $reaction = MessageReaction::firstOrCreate(
            [
                'message_id' => $message->id,
                'user_jid' => $userJid,
                'emoji' => $data['emoji'],
            ],
        );

        $this->broadcaster->broadcastReaction($message, $reaction, 'added');

        return response()->json([
            'ok' => true,
            'reaction' => $reaction,
        ]);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($request->user()?->can('reply chatbot'), 403);

        if ($message->conversation_id !== $conversation->id) {
            abort(404);
        }

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $userJid = $this->resolveAdminJid($request);

        $deleted = MessageReaction::where('message_id', $message->id)
            ->where('user_jid', $userJid)
            ->where('emoji', $data['emoji'])
            ->delete();

        if ($deleted > 0) {
            $this->broadcaster->broadcastReactionRemoved($message, $userJid, $data['emoji']);
        }

        return response()->json([
            'ok' => true,
            'deleted' => $deleted > 0,
        ]);
    }

    private function resolveAdminJid(Request $request): string
    {
        $user = $request->user();
        if ($user?->whatsapp_jid) {
            return $user->whatsapp_jid;
        }
        if ($user?->phone) {
            return 'admin-'.$user->phone;
        }

        return 'admin-'.$user->id;
    }
}
