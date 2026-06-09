<?php

namespace Modules\ChatBot\Services;

use Modules\ChatBot\Events\ChatBotMessageRead;
use Modules\ChatBot\Events\ChatBotMessageReceived;
use Modules\ChatBot\Models\ChatBotConversation;
use Modules\ChatBot\Models\ChatBotMessage;

class ChatBotMessageService
{
    public function sendUserMessage(ChatBotConversation $conversation, string $content, ?int $attachmentMediaId = null): ChatBotMessage
    {
        $message = ChatBotMessage::create([
            'conversation_id' => $conversation->id,
            'role' => ChatBotMessage::ROLE_USER,
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

    public function sendAdminMessage(ChatBotConversation $conversation, string $content, ?int $attachmentMediaId = null): ChatBotMessage
    {
        $message = ChatBotMessage::create([
            'conversation_id' => $conversation->id,
            'role' => ChatBotMessage::ROLE_ADMIN,
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

    public function markAsRead(ChatBotConversation $conversation, int $userId): void
    {
        $unread = ChatBotMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', ChatBotMessage::ROLE_USER)
            ->whereNull('read_at')
            ->get();

        if ($unread->isEmpty()) {
            return;
        }

        ChatBotMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', ChatBotMessage::ROLE_USER)
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
