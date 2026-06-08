<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Laravel\Module as ModuleInstance;

class PackageManager
{
    private const CACHE_KEY = 'packages:enabled';

    /**
     * @return Collection<int, array{slug: string, name: string, menu_label: string, icon: string, menu: array}>
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

        $fresh = collect(Module::allEnabled())->map(function (ModuleInstance $module): array {
            $json = $module->json()->getAttributes();
            $menu = $json['menu'] ?? [];

            if (isset($menu['children'])) {
                foreach ($menu['children'] as $i => $child) {
                    if (isset($child['route'])) {
                        $menu['children'][$i]['href'] = route($child['route'], [], false);
                    }
                }
            }

            return [
                'slug' => $module->getLowerName(),
                'name' => $module->getName(),
                'menu_label' => $module->getName(),
                'icon' => $menu['icon'] ?? 'Package',
                'menu' => $menu,
            ];
        })->values();

        Cache::forever(self::CACHE_KEY, $fresh);

        return $fresh;
    }

    public function isEnabled(string $slug): bool
    {
        return Module::isEnabled($slug);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
