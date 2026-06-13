<?php

namespace Modules\ChatBot\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case File = 'file';
    case Sticker = 'sticker';
    case Location = 'location';
    case Contact = 'contact';

    public function isMediaDownloadable(): bool
    {
        return match ($this) {
            self::Image, self::Video, self::Audio, self::File, self::Sticker => true,
            default => false,
        };
    }
}
