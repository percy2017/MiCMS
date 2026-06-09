<?php

namespace Modules\ChatBot\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => Message::ROLE_USER,
            'type' => MessageType::Text,
            'content' => fake()->sentence(),
            'external_id' => null,
            'metadata' => [],
            'attachment_media_id' => null,
            'delivered_at' => null,
            'read_at' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $a): array => [
            'role' => Message::ROLE_ADMIN,
        ]);
    }
}
