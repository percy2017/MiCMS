<?php

namespace Modules\ChatBot\Services;

use App\Models\Media;
use Modules\ChatBot\Enums\MessageType;
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
            'type' => $this->resolveTypeFromMedia($attachmentMediaId, $content),
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
            'type' => $this->resolveTypeFromMedia($attachmentMediaId, $content),
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

    private function resolveTypeFromMedia(?int $mediaId, string $content): MessageType
    {
        if (! $mediaId) {
            return MessageType::Text;
        }

        $media = Media::find($mediaId);
        if (! $media) {
            return MessageType::Text;
        }

        if (str_starts_with($media->mime_type, 'image/')) {
            return MessageType::Image;
        }
        if (str_starts_with($media->mime_type, 'video/')) {
            return MessageType::Video;
        }
        if (str_starts_with($media->mime_type, 'audio/')) {
            return MessageType::Audio;
        }

        return MessageType::File;
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
