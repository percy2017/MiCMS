<?php

namespace Modules\ChatBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ChatBot\Database\Factories\ConversationFactory;
use Modules\ChatBot\Enums\ConversationStatus;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'conversations';

    protected static function newFactory(): Factory
    {
        return ConversationFactory::new();
    }

    protected $fillable = [
        'channel_id',
        'user_id',
        'external_id',
        'external_thread_id',
        'page_url',
        'status',
        'last_message_at',
        'unread_by_admin',
    ];

    protected $casts = [
        'status' => ConversationStatus::class,
        'last_message_at' => 'datetime',
        'unread_by_admin' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

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
        return $this->hasMany(Message::class)->orderBy('created_at');
    }
}
