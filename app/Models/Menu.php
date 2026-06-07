<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['name', 'location'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_PAGE = 'page';

    public const TARGET_SELF = '_self';

    public const TARGET_BLANK = '_blank';

    /**
     * Get all items for this menu, ordered.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('order')->orderBy('id');
    }

    /**
     * Get only root-level items (no parent).
     *
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /**
     * Get items as a nested tree, grouped by parent_id.
     *
     * @return Collection<int, MenuItem>
     */
    public function nestedItems(): Collection
    {
        $items = $this->items()->with('page:id,slug,is_home,status')->get();

        return $items;
    }

    public function locationLabel(): ?string
    {
        $locations = (array) config('menus.locations', []);

        return $locations[$this->location] ?? null;
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'location_label' => $this->locationLabel(),
            'items_count' => $this->items()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
