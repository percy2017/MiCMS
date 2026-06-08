<?php

namespace App\Http\Controllers\ChatWidget;

use App\Events\ChatMessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatWidget\StoreMessageRequest;
use App\Models\ChatWidgetMessage;
use App\Services\PackageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatWidgetController extends Controller
{
    public function history(Request $request, PackageManager $packages): JsonResponse
    {
        if (! $packages->isEnabled('chat-widget')) {
            return response()->json(['message' => 'Chat widget is disabled.'], 403);
        }

        $sessionId = (string) $request->query('session_id', '');

        if ($sessionId === '') {
            return response()->json(['messages' => []]);
        }

        $messages = ChatWidgetMessage::query()
            ->where('session_id', $sessionId)
            ->orderBy('id')
            ->get()
            ->map(fn (ChatWidgetMessage $m): array => $m->present())
            ->all();

        return response()->json(['messages' => $messages]);
    }

    public function store(StoreMessageRequest $request, PackageManager $packages): JsonResponse
    {
        if (! $packages->isEnabled('chat-widget')) {
            return response()->json(['message' => 'Chat widget is disabled.'], 403);
        }

        $sessionId = $request->string('session_id')->toString() ?: (string) str()->uuid();

        $message = ChatWidgetMessage::create([
            'session_id' => $sessionId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'message' => $request->string('message')->toString(),
            'direction' => ChatWidgetMessage::DIRECTION_INCOMING,
            'ip' => $request->ip(),
        ]);

        broadcast(new ChatMessageReceived($message))->toOthers();

        return response()->json([
            'session_id' => $sessionId,
            'message' => $message->present(),
        ]);
    }
}
