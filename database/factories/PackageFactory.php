<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => fake()->unique()->slug(2),
            'name' => ucwords($name),
            'menu_label' => null,
            'version' => '1.0.0',
            'description' => fake()->sentence(),
            'author' => fake()->name(),
            'category' => Package::CATEGORY_GENERAL,
            'icon' => 'Package',
            'enabled' => false,
            'installed' => true,
            'config' => null,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static
    {
        return $this->state(fn (): array => ['config' => $config]);
    }
}
