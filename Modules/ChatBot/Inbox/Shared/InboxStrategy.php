<?php

namespace Modules\ChatBot\Inbox\Shared;

use Modules\ChatBot\Enums\ChannelType;

interface InboxStrategy
{
    public function type(): ChannelType;

    /**
     * Field name in `channels.config` JSON that identifies the external resource
     * (e.g. 'instance_name' for Evolution, 'session_name' for OpenWa).
     */
    public function externalKeyName(): string;

    /**
     * List available external resources that can be linked to a new inbox.
     * Items include `taken: bool` flag so the UI knows what's already linked.
     *
     * @return array{configured: bool, items: list<array<string, mixed>>, error?: string}
     */
    public function listAvailable(): array;

    /**
     * Initial config for a new inbox linked to the given external key.
     * Returns the values that go into `channels.config`.
     *
     * @param  array<string, mixed>  $externalItem
     * @return array<string, mixed>
     */
    public function defaultsForExternalItem(string $externalKey, array $externalItem): array;

    /**
     * Find the external item detail (e.g. profile_name, profile_picture_url) given the key.
     * Used to prefill the edit form.
     *
     * @return array<string, mixed>|null
     */
    public function findExternalItem(string $externalKey): ?array;

    /**
     * Hook called after the channel is created in DB (before redirect).
     * Use this to push config to the external system (e.g. setWebhook).
     */
    public function onInboxCreated(mixed $channel): void;

    /**
     * Returns metadata about the inbox-level integrations (e.g. webhooks
     * configured on the external instance). Implementations should fetch
     * this from the external API when possible, never hardcode.
     *
     * @return array<string, mixed>|null  null if the integration is not yet set up.
     */
    public function integrationInfo(string $externalKey): ?array;
}
