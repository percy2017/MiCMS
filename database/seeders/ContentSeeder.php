<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureHomeMenu();
        $this->ensureFooterMenu();
    }

    protected function ensureHomeMenu(): void
    {
        $menu = Menu::updateOrCreate(
            ['location' => 'header'],
            ['name' => 'Menú principal'],
        );

        $this->ensureItem($menu, null, 'Inicio', '/', 0);
    }

    protected function ensureFooterMenu(): void
    {
        $menu = Menu::updateOrCreate(
            ['location' => 'footer'],
            ['name' => 'Menú del pie de página'],
        );

        $this->ensureItem($menu, null, 'Inicio', '/', 0);
    }

    protected function ensureItem(Menu $menu, ?int $parentId, string $label, string $url, int $order): void
    {
        MenuItem::updateOrCreate(
            [
                'menu_id' => $menu->id,
                'label' => $label,
                'parent_id' => $parentId,
            ],
            [
                'url' => $url,
                'type' => Menu::TYPE_CUSTOM,
                'page_id' => null,
                'order' => $order,
                'target' => Menu::TARGET_SELF,
            ],
        );
    }
}
