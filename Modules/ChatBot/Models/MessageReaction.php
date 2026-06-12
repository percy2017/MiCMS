<?php

namespace Modules\ChatBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    protected $table = 'message_reactions';

    protected $fillable = [
        'message_id',
        'user_jid',
        'emoji',
        'external_id',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->hasOneThrough(
            Conversation::class,
            Message::class,
            'id',
            'id',
            'message_id',
            'conversation_id',
        );
    }
}
