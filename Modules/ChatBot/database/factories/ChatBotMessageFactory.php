<?php

namespace Modules\ChatBot\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ChatBot\Models\ChatBotConversation;
use Modules\ChatBot\Models\ChatBotMessage;

class ChatBotMessageFactory extends Factory
{
    protected $model = ChatBotMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => ChatBotConversation::factory(),
            'role' => ChatBotMessage::ROLE_USER,
            'content' => fake()->sentence(),
        ];
    }
}
