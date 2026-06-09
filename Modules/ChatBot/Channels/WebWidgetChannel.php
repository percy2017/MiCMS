<?php

namespace Modules\ChatBot\Channels;

use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

class WebWidgetChannel implements ChannelInterface
{
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
        return [
            [
                'key' => 'title',
                'label' => 'Título',
                'type' => 'text',
                'required' => true,
            ],
            [
                'key' => 'subtitle',
                'label' => 'Subtítulo',
                'type' => 'text',
                'required' => false,
            ],
            [
                'key' => 'greeting',
                'label' => 'Mensaje de bienvenida',
                'type' => 'textarea',
                'required' => false,
            ],
            [
                'key' => 'position',
                'label' => 'Posición',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'left', 'label' => 'Izquierda'],
                    ['value' => 'right', 'label' => 'Derecha'],
                ],
            ],
            [
                'key' => 'require_auth',
                'label' => 'Requerir autenticación',
                'type' => 'boolean',
                'required' => false,
            ],
            [
                'key' => 'show_typing',
                'label' => 'Mostrar indicador de escritura',
                'type' => 'boolean',
                'required' => false,
            ],
            [
                'key' => 'offline_message',
                'label' => 'Mensaje fuera de horario',
                'type' => 'textarea',
                'required' => false,
            ],
        ];
    }

    public function boot(Channel $channel): void
    {
        //
    }

    public function shutdown(Channel $channel): void
    {
        //
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
        $settings = $channel->settings ?? [];

        return [
            'configured' => ! empty($settings['title']),
            'title' => $settings['title'] ?? 'No configurado',
        ];
    }
}
