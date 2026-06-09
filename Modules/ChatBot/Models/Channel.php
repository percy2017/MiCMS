<?php

namespace Modules\ChatBot\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ChatBot\Database\Factories\ChannelFactory;
use Modules\ChatBot\Enums\ChannelType;

class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'channels';

    protected static function newFactory(): Factory
    {
        return ChannelFactory::new();
    }

    protected $fillable = [
        'type',
        'name',
        'enabled',
        'config',
        'settings',
        'sort',
    ];

    protected $casts = [
        'type' => ChannelType::class,
        'enabled' => 'boolean',
        'config' => 'encrypted:array',
        'settings' => 'array',
        'sort' => 'integer',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
