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
        'allowed_domains',
        'public_key',
        'sort',
    ];

    protected $casts = [
        'type' => ChannelType::class,
        'enabled' => 'boolean',
        'config' => 'encrypted:array',
        'settings' => 'array',
        'allowed_domains' => 'array',
        'sort' => 'integer',
    ];

    public static function generatePublicKey(): string
    {
        return substr(bin2hex(random_bytes(8)), 0, 16);
    }

    protected static function booted(): void
    {
        static::creating(function (Channel $channel): void {
            if ($channel->type === ChannelType::WebWidget && empty($channel->public_key)) {
                $channel->public_key = self::generatePublicKey();
            }
        });
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
