<?php

namespace Modules\ChatBot\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'user_id' => User::factory(),
            'external_id' => null,
            'visitor_name' => fake()->name(),
            'visitor_email' => fake()->email(),
            'page_url' => fake()->url(),
            'status' => ConversationStatus::Open,
            'assigned_to' => null,
            'last_message_at' => now(),
            'unread_by_admin' => 0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $a): array => [
            'status' => ConversationStatus::Closed,
        ]);
    }
}
