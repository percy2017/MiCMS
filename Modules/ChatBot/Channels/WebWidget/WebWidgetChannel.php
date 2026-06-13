<?php

namespace Modules\ChatBot\Channels\WebWidget;

use Modules\ChatBot\Channels\ChannelInterface;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

/**
 * Canal Widget Web (chat embebido en el sitio).
 *
 * El widget no tiene API externa — los mensajes se entregan en tiempo real
 * vía Reverb (broadcasting). Las acciones de enviar/recibir son no-ops
 * porque el controlador SessionController maneja la creación de mensajes.
 */
class WebWidgetChannel implements ChannelInterface
{
    public function __construct(
        private readonly WebWidgetConfigProvider $configProvider = new WebWidgetConfigProvider,
        private readonly WebWidgetStatsProvider $statsProvider = new WebWidgetStatsProvider,
    ) {}

    public function type(): ChannelType
    {
        return ChannelType::WebWidget;
    }

    public function name(): string
    {
        return 'Widget Web';
    }

    public function description(): string
    {
        return 'Chat widget integrado en el sitio web. Los visitantes chatean desde el navegador.';
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function accentColor(): string
    {
        return '#2563eb';
    }

    public function configFields(): array
    {
        return [];
    }

    public function settingsFields(): array
    {
        return $this->configProvider->settingsFields();
    }

    public function boot(Channel $channel): void
    {
        // nothing to boot
    }

    public function shutdown(Channel $channel): void
    {
        // nothing to shutdown
    }

    public function sendMessage(Conversation $conversation, Message $message): array
    {
        // Web widget messages are delivered via Reverb (real-time), not via external API
        return ['ok' => true];
    }

    public function processIncoming(array $payload, Channel $channel): ?Message
    {
        // Web widget handles its own message creation through controllers
        return null;
    }

    public function stats(Channel $channel): array
    {
        return $this->statsProvider->stats($channel);
    }
}
