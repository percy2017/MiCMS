<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'label' => fake()->words(2, true),
            'url' => '/'.fake()->slug(2),
            'type' => 'custom',
            'page_id' => null,
            'order' => 0,
            'target' => '_self',
        ];
    }

    public function page(?int $pageId = null): static
    {
        return $this->state(fn (): array => [
            'type' => Menu::TYPE_PAGE,
            'page_id' => $pageId,
            'url' => null,
        ]);
    }

    public function external(?string $url = null): static
    {
        return $this->state(fn (): array => [
            'url' => $url ?? fake()->url(),
            'target' => '_blank',
        ]);
    }
}
