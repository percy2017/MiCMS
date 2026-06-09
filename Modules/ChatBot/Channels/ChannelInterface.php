<?php

namespace Modules\ChatBot\Channels;

use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

interface ChannelInterface
{
    public function type(): ChannelType;

    public function name(): string;

    public function description(): string;

    public function icon(): string;

    public function accentColor(): string;

    /**
     * Provider-specific config fields (api keys, instance names, etc.)
     *
     * @return array<int, array{key:string,label:string,type:string,required:bool,help?:string,placeholder?:string,options?:array}>
     */
    public function configFields(): array;

    /**
     * UI-specific settings fields (avatar, position, etc.)
     *
     * @return array<int, array{key:string,label:string,type:string,required:bool,help?:string,options?:array}>
     */
    public function settingsFields(): array;

    public function boot(Channel $channel): void;

    public function shutdown(Channel $channel): void;

    /**
     * Send a message through the channel.
     *
     * @return array{ok: bool, provider_id?: string, error?: string, raw?: array}
     */
    public function sendMessage(Conversation $conversation, Message $message): array;

    /**
     * Normalize and persist an incoming payload from the provider.
     */
    public function processIncoming(array $payload, Channel $channel): ?Message;

    /**
     * Stats for the admin UI (connected, last_seen, etc.)
     *
     * @return array<string, mixed>
     */
    public function stats(Channel $channel): array;
}
