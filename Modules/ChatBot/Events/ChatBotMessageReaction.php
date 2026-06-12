<?php

namespace Modules\ChatBot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;

class ChatBotMessageReaction implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Message $message,
        public MessageReaction $reaction,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chatbot.admin')];
    }

    public function broadcastAs(): string
    {
        return 'ChatBotMessageReaction';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'reaction' => [
                'id' => $this->reaction->id,
                'emoji' => $this->reaction->emoji,
                'user_jid' => $this->reaction->user_jid,
            ],
        ];
    }
}
