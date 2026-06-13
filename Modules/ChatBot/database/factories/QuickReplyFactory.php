<?php

namespace Modules\ChatBot\Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\ChatBot\Models\QuickReply;

class QuickReplyFactory extends Factory
{
    protected $model = QuickReply::class;

    public function definition(): array
    {
        $name = Str::slug(fake()->unique()->words(2, true));

        return [
            'shortcut' => $name,
            'title' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'category' => fake()->randomElement(['saludos', 'soporte', 'precios', 'promos', null]),
            'media_id' => null,
            'sort' => 0,
            'enabled' => true,
            'created_by' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }

    public function mediaOnly(): static
    {
        return $this->state(fn (): array => [
            'content' => null,
            'media_id' => Media::factory(),
        ]);
    }
}
