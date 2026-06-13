<?php

namespace Modules\ChatBot\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'type' => ChannelType::WebWidget,
            'name' => fake()->word(),
            'enabled' => true,
            'config' => [],
            'settings' => [],
            'sort' => 0,
        ];
    }

    public function webWidget(): static
    {
        return $this->state(fn (array $a): array => [
            'type' => ChannelType::WebWidget,
            'name' => 'Widget Web',
            'config' => [],
            'settings' => [
                'title' => 'Asistente virtual',
                'subtitle' => 'Te respondemos en minutos',
                'greeting' => '¡Hola! ¿En qué podemos ayudarte?',
                'position' => 'right',
                'require_auth' => true,
                'show_typing' => true,
                'offline_message' => 'Estamos fuera de horario.',
            ],
            'allowed_domains' => [],
            'public_key' => Channel::generatePublicKey(),
        ]);
    }

    public function evolution(): static
    {
        return $this->state(fn (array $a): array => [
            'type' => ChannelType::Evolution,
            'name' => 'WhatsApp',
            'config' => [
                'server_url' => env('EVOLUTION_DEFAULT_SERVER_URL', 'https://evolution.example.com'),
                'api_key' => env('EVOLUTION_DEFAULT_API_KEY', ''),
                'instance_name' => env('EVOLUTION_DEFAULT_INSTANCE', 'percy-whatsapp'),
            ],
            'settings' => [
                'display_name' => 'WhatsApp',
                'auto_reply' => '',
            ],
        ]);
    }

    public function openwa(): static
    {
        return $this->state(fn (array $a): array => [
            'type' => ChannelType::OpenWa,
            'name' => 'openwa-test',
            'config' => [
                'session_name' => 'openwa-test',
            ],
            'settings' => [
                'display_name' => 'OpenWA Test',
                'auto_reply' => '',
            ],
        ]);
    }
}
