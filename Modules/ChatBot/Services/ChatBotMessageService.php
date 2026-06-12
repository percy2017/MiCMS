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

    /**
     * Construye un Message admin en memoria (NO persiste).
     * Usar antes de enviar a WhatsApp; si el envío falla, descartar el modelo.
     */
    public function buildAdminMessage(Conversation $conversation, string $content, ?int $attachmentMediaId = null): Message
    {
        $message = new Message([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_ADMIN,
            'type' => $this->resolveTypeFromMedia($attachmentMediaId, $content),
            'content' => $content,
            'attachment_media_id' => $attachmentMediaId,
        ]);

        return $message;
    }

    /**
     * Persiste un Message admin ya enviado exitosamente a WhatsApp.
     * Marca como entregado si se tiene provider_id, y broadcastea.
     */
    public function persistAdminMessage(Message $message, ?string $providerId = null): Message
    {
        $message->save();

        if ($providerId) {
            $message->update([
                'external_id' => $providerId,
                'delivered_at' => now(),
            ]);
        }

        $message->conversation->update([
            'last_message_at' => $message->created_at,
            'unread_by_admin' => 0,
        ]);

        ChatBotMessageReceived::dispatch($message);

        return $message;
    }

    public function sendAdminMessage(Conversation $conversation, string $content, ?int $attachmentMediaId = null): Message
    {
        $message = $this->buildAdminMessage($conversation, $content, $attachmentMediaId);

        return $this->persistAdminMessage($message);
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
