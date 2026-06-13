<?php

namespace Modules\ChatBot\Channels\Evolution;

use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Events\ChatBotMessageReaction as ChatBotMessageReactionEvent;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;

class EvolutionReactionHandler
{
    /**
     * Procesa un evento `messages.reaction` de Evolution API.
     *
     * Payload típico:
     *   {
     *     "event": "messages.reaction",
     *     "data": {
     *       "key": { "remoteJid": "...", "fromMe": false, "id": "MSG_ID" },
     *       "reaction": {
     *         "text": "❤️",                       // emoji (vacío = remoción)
     *         "key": { "id": "REACTION_ID", "remoteJid": "...", "fromMe": false }
     *       }
     *     }
     *   }
     *
     * @return array{action: string, message: ?Message, reaction: ?MessageReaction}
     */
    public function process(array $payload, Channel $channel): array
    {
        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];
        $reaction = $data['reaction'] ?? [];

        $messageId = $key['id'] ?? null;
        $emoji = $reaction['text'] ?? null;
        $reactionId = $reaction['key']['id'] ?? null;
        $fromMe = (bool) ($key['fromMe'] ?? $reaction['key']['fromMe'] ?? false);
        $remoteJid = $key['remoteJid'] ?? $reaction['key']['remoteJid'] ?? null;

        Log::info('EvolutionReactionHandler: reaction received', [
            'channel_id' => $channel->id,
            'message_id' => $messageId,
            'remote_jid' => $remoteJid,
            'emoji' => $emoji,
            'reaction_id' => $reactionId,
            'from_me' => $fromMe,
        ]);

        if (! $messageId || $remoteJid === null) {
            return ['action' => 'skipped', 'message' => null, 'reaction' => null];
        }

        $message = $this->findMessageByExternalId($messageId, $remoteJid, $channel->id);
        if (! $message) {
            Log::info('EvolutionReactionHandler: reaction for unknown message', [
                'message_id' => $messageId,
                'remote_jid' => $remoteJid,
                'channel_id' => $channel->id,
            ]);

            return ['action' => 'skipped', 'message' => null, 'reaction' => null];
        }

        $userJid = $fromMe ? 'admin-self' : $remoteJid;
        $action = 'added';

        if ($emoji === null || $emoji === '' || $emoji === false) {
            $deleted = $this->removeReaction($message, $userJid, $reactionId);
            if ($deleted > 0) {
                $this->broadcastReactionRemoved($message, $userJid, (string) ($reaction['previousText'] ?? ''));
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

        ChatBotMessageReactionEvent::dispatch($message, $model, $action);

        return ['action' => $action, 'message' => $message, 'reaction' => $model];
    }

    /**
     * Busca el Message local por `external_id` (id de WhatsApp). Como el `external_id`
     * es único por instancia, se valida que pertenezca a una Conversation del mismo canal.
     */
    private function findMessageByExternalId(string $externalId, string $remoteJid, int $channelId): ?Message
    {
        $message = Message::withTrashed()
            ->where('external_id', $externalId)
            ->whereHas('conversation', function ($q) use ($channelId, $remoteJid) {
                $q->where('channel_id', $channelId)
                    ->where('external_id', $remoteJid);
            })
            ->first();

        if ($message) {
            return $message;
        }

        return Message::withTrashed()
            ->whereHas('conversation', function ($q) use ($channelId, $remoteJid) {
                $q->where('channel_id', $channelId)
                    ->where('external_id', $remoteJid);
            })
            ->where(function ($q) use ($externalId) {
                $q->where('metadata->wa_message_id', $externalId)
                    ->orWhere('metadata->reaction->id', $externalId);
            })
            ->first();
    }

    /**
     * Elimina reacciones de un mensaje según los criterios dados.
     *
     * @return int número de filas eliminadas
     */
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

    private function broadcastReactionRemoved(Message $message, string $userJid, string $emoji): void
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
