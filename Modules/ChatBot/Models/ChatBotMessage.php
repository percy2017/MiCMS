<?php

namespace Modules\ChatBot\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ChatBot\Database\Factories\ChatBotMessageFactory;

class ChatBotMessage extends Model
{
    use HasFactory;

    protected $table = 'chatbot_messages';

    protected static function newFactory(): Factory
    {
        return ChatBotMessageFactory::new();
    }

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SYSTEM = 'system';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'attachment_media_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatBotConversation::class, 'conversation_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'attachment_media_id');
    }
}
