<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['menu_id', 'parent_id', 'label', 'url', 'type', 'page_id', 'order', 'target'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Resolve the actual URL to use in the frontend.
     * For type=page items, the URL is the page's publicUrl().
     */
    public function resolvedUrl(): string
    {
        if ($this->type === Menu::TYPE_PAGE && $this->page) {
            return $this->page->publicUrl();
        }

        return (string) $this->url;
    }

    public function isExternal(): bool
    {
        if ($this->type === Menu::TYPE_PAGE && $this->page) {
            return false;
        }

        $url = (string) $this->url;

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'parent_id' => $this->parent_id,
            'label' => $this->label,
            'url' => $this->url,
            'resolved_url' => $this->resolvedUrl(),
            'type' => $this->type,
            'page_id' => $this->page_id,
            'order' => $this->order,
            'target' => $this->target,
            'is_external' => $this->isExternal(),
            'children' => $this->relationLoaded('children')
                ? $this->children->map(fn (MenuItem $child) => $child->present())->all()
                : [],
        ];
    }
}
