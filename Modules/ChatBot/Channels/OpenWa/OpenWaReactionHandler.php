<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Events\ChatBotMessageReaction as ChatBotMessageReactionEvent;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;

/**
 * Procesa eventos de reacción de OpenWA.
 *
 * OpenWA no expone eventos separados de reaction como Evolution. En su lugar,
 * los emojis llegan como un mensaje normal de tipo `reaction` con metadata.
 * Este handler procesa el formato que llega en `message.received` cuando
 * OpenWA lo soporta (versiones futuras) o se dispara manualmente vía API.
 */
class OpenWaReactionHandler
{
    /**
     * Procesa un payload de reacción.
     *
     * @param  array{
     *     messageId: string,
     *     emoji: string,
     *     chatId: string,
     *     fromMe?: bool,
     *     reactionId?: ?string,
     * }  $data
     * @return array{action: string, message: ?Message, reaction: ?MessageReaction}
     */
    public function process(array $data, Channel $channel): array
    {
        $messageId = $data['messageId'] ?? null;
        $emoji = $data['emoji'] ?? null;
        $chatId = $data['chatId'] ?? null;
        $fromMe = (bool) ($data['fromMe'] ?? false);
        $reactionId = $data['reactionId'] ?? null;

        Log::info('OpenWaReactionHandler: reaction received', [
            'channel_id' => $channel->id,
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'emoji' => $emoji,
            'reaction_id' => $reactionId,
            'from_me' => $fromMe,
        ]);

        if (! $messageId || ! $chatId) {
            return ['action' => 'skipped', 'message' => null, 'reaction' => null];
        }

        $message = $this->findMessage($messageId, $chatId, $channel->id);
        if (! $message) {
            Log::info('OpenWaReactionHandler: reaction for unknown message', [
                'message_id' => $messageId,
                'chat_id' => $chatId,
                'channel_id' => $channel->id,
            ]);

            return ['action' => 'skipped', 'message' => null, 'reaction' => null];
        }

        $userJid = $fromMe ? 'admin-self' : $chatId;

        if ($emoji === null || $emoji === '') {
            $deleted = $this->removeReaction($message, $userJid, $reactionId);
            if ($deleted > 0) {
                $this->broadcastRemoved($message, $userJid, '');
            }

            return ['action' => $deleted > 0 ? 'removed' : 'skipped', 'message' => $message, 'reaction' => null];
        }

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_jid', $userJid)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            return ['action' => 'exists', 'message' => $message, 'reaction' => $existing];
        }

        $model = MessageReaction::create([
            'message_id' => $message->id,
            'user_jid' => $userJid,
            'emoji' => $emoji,
            'external_id' => $reactionId,
        ]);

        ChatBotMessageReactionEvent::dispatch($message, $model, 'added');

        return ['action' => 'added', 'message' => $message, 'reaction' => $model];
    }

    private function findMessage(string $messageId, string $chatId, int $channelId): ?Message
    {
        return Message::withTrashed()
            ->where('external_id', $messageId)
            ->whereHas('conversation', function ($q) use ($channelId, $chatId) {
                $q->where('channel_id', $channelId)
                    ->where('external_id', $chatId);
            })
            ->first();
    }

    private function removeReaction(Message $message, string $userJid, ?string $reactionId): int
    {
        $query = MessageReaction::where('message_id', $message->id)->where('user_jid', $userJid);

        if ($reactionId) {
            $query->where(function ($q) use ($reactionId) {
                $q->where('external_id', $reactionId)->orWhereNull('external_id');
            });
        }

        return $query->delete();
    }

    private function broadcastRemoved(Message $message, string $userJid, string $emoji): void
    {
        $placeholder = new MessageReaction([
            'message_id' => $message->id,
            'user_jid' => $userJid,
            'emoji' => $emoji,
        ]);
        $placeholder->id = 0;

        ChatBotMessageReactionEvent::dispatch($message, $placeholder, 'removed');
    }
}
