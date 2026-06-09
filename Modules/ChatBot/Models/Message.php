<?php

namespace Modules\ChatBot\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ChatBot\Database\Factories\MessageFactory;
use Modules\ChatBot\Enums\MessageType;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $table = 'messages';

    protected static function newFactory(): Factory
    {
        return MessageFactory::new();
    }

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SYSTEM = 'system';

    public const ROLE_BOT = 'bot';

    protected $fillable = [
        'conversation_id',
        'role',
        'type',
        'content',
        'external_id',
        'metadata',
        'attachment_media_id',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'type' => MessageType::class,
        'metadata' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'attachment_media_id');
    }
}
