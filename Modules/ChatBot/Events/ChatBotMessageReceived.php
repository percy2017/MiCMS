<?php

namespace Modules\ChatBot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\ChatBot\Models\Message;

class ChatBotMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Message $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chatbot.admin')];
    }

    public function broadcastAs(): string
    {
        return 'ChatBotMessageReceived';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('conversation.channel', 'attachment');

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'role' => $this->message->role,
                'type' => $this->message->type?->value,
                'content' => $this->message->content,
                'attachment_url' => $this->message->attachment?->url(),
                'attachment_mime' => $this->message->attachment?->mime_type,
                'attachment_name' => $this->message->attachment?->name,
                'attachment_size' => $this->message->attachment?->size,
                'metadata' => $this->message->metadata,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
            'conversation' => [
                'id' => $this->message->conversation->id,
                'channel_id' => $this->message->conversation->channel_id,
                'channel_name' => $this->message->conversation->channel
                    ? ($this->message->conversation->channel->type->value === 'evolution'
                        ? ($this->message->conversation->channel->config['instance_name'] ?? $this->message->conversation->channel->settings['display_name'] ?? $this->message->conversation->channel->name)
                        : ($this->message->conversation->channel->settings['display_name'] ?? $this->message->conversation->channel->name))
                    : null,
                'visitor_name' => $this->message->conversation->visitor_name,
                'visitor_email' => $this->message->conversation->visitor_email,
                'unread_by_admin' => $this->message->conversation->unread_by_admin,
            ],
        ];
    }
}
