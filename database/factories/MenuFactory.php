<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Menu',
            'location' => fake()->unique()->slug(1),
        ];
    }

    public function location(string $location): static
    {
        return $this->state(fn (): array => ['location' => $location]);
    }
}
