<?php

namespace Modules\ChatBot\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Closed => 'Cerrada',
            self::Archived => 'Archivada',
        };
    }
}
