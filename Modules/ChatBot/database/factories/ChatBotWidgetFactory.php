<?php

namespace Modules\ChatBot\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ChatBot\Models\ChatBotWidget;

class ChatBotWidgetFactory extends Factory
{
    protected $model = ChatBotWidget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enabled' => true,
            'title' => 'Asistente virtual',
            'subtitle' => 'Te respondemos en minutos',
            'greeting' => '¡Hola! ¿En qué podemos ayudarte?',
            'position' => 'right',
            'require_auth' => true,
            'show_typing' => true,
            'offline_message' => 'Estamos fuera de horario.',
        ];
    }
}
