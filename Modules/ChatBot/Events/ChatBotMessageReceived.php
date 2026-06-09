<?php

namespace Modules\ChatBot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\ChatBot\Models\ChatBotMessage;

class ChatBotMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public ChatBotMessage $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chatbot.admin')];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('conversation');

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'role' => $this->message->role,
                'content' => $this->message->content,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
            'conversation' => [
                'id' => $this->message->conversation->id,
                'visitor_name' => $this->message->conversation->visitor_name,
                'visitor_email' => $this->message->conversation->visitor_email,
                'unread_by_admin' => $this->message->conversation->unread_by_admin,
            ],
        ];
    }
}
