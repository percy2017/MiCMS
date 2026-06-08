<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'menu_label', 'version', 'description', 'author', 'category', 'icon', 'enabled', 'installed', 'config'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    public const CATEGORY_COMMUNICATION = 'communication';

    public const CATEGORY_BUSINESS = 'business';

    public const CATEGORY_GENERAL = 'general';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'installed' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeInstalled(Builder $query): Builder
    {
        return $query->where('installed', true);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_COMMUNICATION => 'Comunicación',
            self::CATEGORY_BUSINESS => 'Negocios',
            self::CATEGORY_GENERAL => 'General',
            default => ucfirst((string) $this->category),
        };
    }

    public function menuLabel(): string
    {
        return $this->menu_label ?? $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'menu_label' => $this->menu_label,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'icon' => $this->icon,
            'enabled' => (bool) $this->enabled,
            'installed' => (bool) $this->installed,
            'config' => $this->config ?? [],
        ];
    }
}
