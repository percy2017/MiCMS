<?php

namespace Modules\ChatBot\Services;

use Modules\ChatBot\Events\ChatBotMessageReaction;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;

class ReactionBroadcaster
{
    public function broadcastReaction(Message $message, MessageReaction $reaction, string $action): void
    {
        ChatBotMessageReaction::dispatch($message, $reaction, $action);
    }

    public function broadcastReactionRemoved(Message $message, string $userJid, string $emoji): void
    {
        $placeholder = new MessageReaction([
            'message_id' => $message->id,
            'user_jid' => $userJid,
            'emoji' => $emoji,
        ]);
        $placeholder->id = 0;

        ChatBotMessageReaction::dispatch($message, $placeholder, 'removed');
    }
}
