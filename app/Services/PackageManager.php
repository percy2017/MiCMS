<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PackageManager
{
    private const CACHE_KEY = 'packages:enabled';

    /**
     * @return Collection<int, Package>
     */
    public function enabled(): Collection
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof Collection) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $fresh = Package::query()
            ->enabled()
            ->installed()
            ->orderBy('name')
            ->get();

        Cache::forever(self::CACHE_KEY, $fresh);

        return $fresh;
    }

    public function isEnabled(string $slug): bool
    {
        return $this->enabled()->contains(fn (Package $p): bool => $p->slug === $slug);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
