<?php

namespace Modules\ChatBot\Models;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ChatBot\Database\Factories\QuickReplyFactory;

class QuickReply extends Model
{
    /** @use HasFactory<QuickReplyFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'quick_replies';

    protected static function newFactory(): Factory
    {
        return QuickReplyFactory::new();
    }

    protected $fillable = [
        'shortcut',
        'title',
        'content',
        'category',
        'media_id',
        'sort',
        'enabled',
        'created_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort' => 'integer',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
