<?php

namespace Database\Factories;

use App\Models\ChatWidgetMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatWidgetMessage>
 */
class ChatWidgetMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'message' => fake()->sentence(),
            'direction' => ChatWidgetMessage::DIRECTION_INCOMING,
            'ip' => fake()->ipv4(),
        ];
    }

    public function incoming(): static
    {
        return $this->state(fn (): array => ['direction' => ChatWidgetMessage::DIRECTION_INCOMING]);
    }

    public function outgoing(): static
    {
        return $this->state(fn (): array => ['direction' => ChatWidgetMessage::DIRECTION_OUTGOING]);
    }

    public function anonymous(): static
    {
        return $this->state(fn (): array => ['name' => null, 'email' => null]);
    }
}
