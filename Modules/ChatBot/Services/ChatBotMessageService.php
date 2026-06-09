<?php

namespace Modules\ChatBot\Services;

use Modules\ChatBot\Events\ChatBotMessageRead;
use Modules\ChatBot\Events\ChatBotMessageReceived;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

class ChatBotMessageService
{
    public function sendUserMessage(Conversation $conversation, string $content, ?int $attachmentMediaId = null): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'content' => $content,
            'attachment_media_id' => $attachmentMediaId,
        ]);

        $conversation->update([
            'last_message_at' => $message->created_at,
            'unread_by_admin' => $conversation->unread_by_admin + 1,
        ]);

        ChatBotMessageReceived::dispatch($message);

        return $message;
    }

    public function sendAdminMessage(Conversation $conversation, string $content, ?int $attachmentMediaId = null): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_ADMIN,
            'content' => $content,
            'attachment_media_id' => $attachmentMediaId,
        ]);

        $conversation->update([
            'last_message_at' => $message->created_at,
            'unread_by_admin' => 0,
        ]);

        ChatBotMessageReceived::dispatch($message);

        return $message;
    }

    public function markAsRead(Conversation $conversation, int $userId): void
    {
        $unread = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', Message::ROLE_USER)
            ->whereNull('read_at')
            ->get();

        if ($unread->isEmpty()) {
            return;
        }

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', Message::ROLE_USER)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->update(['unread_by_admin' => 0]);

        ChatBotMessageRead::dispatch(
            $conversation->id,
            $unread->pluck('id')->all(),
            $conversation->user_id,
        );
    }
}
