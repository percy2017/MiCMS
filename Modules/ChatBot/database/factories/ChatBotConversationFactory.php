<?php

namespace Modules\ChatBot\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ChatBot\Models\ChatBotConversation;

class ChatBotConversationFactory extends Factory
{
    protected $model = ChatBotConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'visitor_name' => $user->name,
            'visitor_email' => $user->email,
            'page_url' => '/',
            'status' => ChatBotConversation::STATUS_OPEN,
            'last_message_at' => now(),
            'unread_by_admin' => 0,
        ];
    }
}
