<?php

namespace Modules\ChatBot\Enums;

enum ChannelType: string
{
    case WebWidget = 'web_widget';
    case Evolution = 'evolution';
    case OpenWa = 'openwa';

    public function label(): string
    {
        return match ($this) {
            self::WebWidget => 'Web Widget',
            self::Evolution => 'WhatsApp (Evolution API)',
            self::OpenWa => 'WhatsApp (OpenWA)',
        };
    }
}
