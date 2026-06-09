<?php

namespace Modules\ChatBot\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ChatBot\Database\Factories\ChatBotWidgetFactory;

class ChatBotWidget extends Model
{
    use HasFactory;

    protected $table = 'chatbot_widgets';

    protected static function newFactory(): Factory
    {
        return ChatBotWidgetFactory::new();
    }

    protected $fillable = [
        'enabled',
        'title',
        'subtitle',
        'greeting',
        'position',
        'avatar_media_id',
        'require_auth',
        'show_typing',
        'offline_message',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'require_auth' => 'boolean',
        'show_typing' => 'boolean',
    ];

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'enabled' => true,
            'title' => 'Asistente virtual',
            'subtitle' => 'Te respondemos en minutos',
            'greeting' => '¡Hola! 👋 ¿En qué podemos ayudarte?',
            'position' => 'right',
            'require_auth' => true,
            'show_typing' => true,
            'offline_message' => 'Estamos fuera de horario. Déjanos tu mensaje y te respondemos en horario laboral.',
        ]);
    }
}
