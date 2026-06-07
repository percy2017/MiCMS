<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'status' => Page::STATUS_DRAFT,
            'puck_data' => null,
            'published_at' => null,
            'is_home' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => Page::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function withFixture(): static
    {
        return $this->state(fn (): array => [
            'puck_data' => [
                'content' => [
                    [
                        'type' => 'HeadingBlock',
                        'props' => [
                            'id' => 'HeadingBlock-'.Str::random(6),
                            'level' => 'h1',
                            'align' => 'left',
                            'children' => fake()->sentence(4),
                        ],
                    ],
                    [
                        'type' => 'TextBlock',
                        'props' => [
                            'id' => 'TextBlock-'.Str::random(6),
                            'content' => '<p>'.fake()->paragraph().'</p>',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => 'Sample root']],
                'zones' => [],
            ],
        ]);
    }
}
