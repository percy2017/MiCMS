<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedLandingPage();
        $this->seedMenus();

        $this->command?->info('Content seedeado: settings, landing page, header/footer menus.');
    }

    protected function seedSettings(): void
    {
        Setting::set('site_name', config('app.name'), Setting::TYPE_STRING);
        Setting::set('site_tagline', 'CMS moderno', Setting::TYPE_STRING);
    }

    protected function seedLandingPage(): void
    {
        Page::query()->update(['is_home' => false]);

        $user = User::query()->firstOrFail();

        Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'user_id' => $user->id,
                'title' => 'Bienvenido a '.config('app.name'),
                'slug' => 'home',
                'status' => Page::STATUS_PUBLISHED,
                'is_home' => true,
                'published_at' => now(),
                'puck_data' => [
                    'content' => $this->landingBlocks(),
                    'root' => [
                        'props' => [
                            'title' => 'Inicio',
                        ],
                    ],
                    'zones' => [],
                ],
            ],
        );
    }

    protected function seedMenus(): void
    {
        $header = Menu::updateOrCreate(
            ['location' => 'header'],
            ['name' => 'Menú principal'],
        );
        $this->ensureItem($header, null, 'Inicio', '/', 0);

        $footer = Menu::updateOrCreate(
            ['location' => 'footer'],
            ['name' => 'Menú del pie de página'],
        );
        $this->ensureItem($footer, null, 'Inicio', '/', 0);
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function landingBlocks(): array
    {
        return [
            [
                'type' => 'HeadingBlock',
                'props' => $this->props('landing-hero-eyebrow', ['level' => 'h4', 'align' => 'center', 'children' => 'CMS moderno']),
            ],
            [
                'type' => 'HeadingBlock',
                'props' => $this->props('landing-hero-title', [
                    'level' => 'h1',
                    'align' => 'center',
                    'children' => 'Diseña tus páginas sin tocar código',
                ]),
            ],
            [
                'type' => 'TextBlock',
                'props' => $this->props('landing-hero-subtitle', [
                    'content' => '<p>Una plataforma moderna construida con Laravel, Inertia.js y Puck. Crea landing pages, gestiona medios, organiza menús y más.</p>',
                    'align' => 'center',
                ]),
            ],
            [
                'type' => 'ButtonBlock',
                'props' => $this->props('landing-cta-primary', [
                    'text' => 'Ir al Panel',
                    'url' => '/admin',
                    'variant' => 'primary',
                    'align' => 'center',
                ]),
            ],
            [
                'type' => 'HeadingBlock',
                'props' => $this->props('landing-features-title', ['level' => 'h2', 'align' => 'center', 'children' => 'Todo lo que necesitas']),
            ],
            [
                'type' => 'TextBlock',
                'props' => $this->props('landing-features-subtitle', [
                    'content' => '<p>Un CMS completo con editor visual, medios, menús dinámicos y módulos extensibles.</p>',
                    'align' => 'center',
                ]),
            ],
            [
                'type' => 'ColumnsBlock',
                'props' => $this->props('landing-features-grid', ['columns' => 3, 'gap' => 'lg']),
            ],
            [
                'type' => 'ColumnsBlock',
                'props' => $this->props('landing-cta-row', ['columns' => 2, 'gap' => 'md']),
            ],
            [
                'type' => 'DividerBlock',
                'props' => $this->props('landing-divider', ['style' => 'solid']),
            ],
            [
                'type' => 'TextBlock',
                'props' => $this->props('landing-footer-note', [
                    'content' => '<p>Construido con Laravel 13, Inertia v3, Puck, React 19 y Tailwind 4.</p>',
                    'align' => 'center',
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function props(string $id, array $block): array
    {
        return [
            'id' => $id,
            ...$block,
            'padding' => 'md',
            'marginBottom' => 'none',
            'backgroundColor' => 'transparent',
            'borderRadius' => 'none',
        ];
    }
}
