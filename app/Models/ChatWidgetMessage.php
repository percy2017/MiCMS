<?php

namespace App\Models;

use Database\Factories\ChatWidgetMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['session_id', 'name', 'email', 'message', 'direction', 'ip'])]
class ChatWidgetMessage extends Model
{
    /** @use HasFactory<ChatWidgetMessageFactory> */
    use HasFactory;

    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTION_OUTGOING = 'outgoing';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'name' => $this->name,
            'email' => $this->email,
            'message' => $this->message,
            'direction' => $this->direction,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
