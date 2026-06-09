<?php

namespace Modules\ChatBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ChatBot\Database\Factories\ChatBotConversationFactory;

class ChatBotConversation extends Model
{
    use HasFactory;

    protected $table = 'chatbot_conversations';

    protected static function newFactory(): Factory
    {
        return ChatBotConversationFactory::new();
    }

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'visitor_name',
        'visitor_email',
        'page_url',
        'status',
        'assigned_to',
        'last_message_at',
        'unread_by_admin',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatBotMessage::class, 'conversation_id');
    }
}
