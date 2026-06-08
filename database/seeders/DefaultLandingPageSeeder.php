<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultLandingPageSeeder extends Seeder
{
    public function run(): void
    {
        Page::query()->update(['is_home' => false]);

        $user = User::query()->firstOrFail();

        $page = Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'user_id' => $user->id,
                'title' => 'Bienvenido a '.config('app.name'),
                'slug' => 'home',
                'status' => Page::STATUS_PUBLISHED,
                'is_home' => true,
                'published_at' => now(),
                'puck_data' => [
                    'content' => $this->content(),
                    'root' => [
                        'props' => [
                            'title' => 'Inicio',
                        ],
                    ],
                    'zones' => [],
                ],
            ],
        );

        $this->command?->info("Página de inicio '{$page->title}' lista en /");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function content(): array
    {
        return [
            [
                'type' => 'HeadingBlock',
                'props' => $this->props(
                    'landing-hero-eyebrow',
                    ['level' => 'h4', 'align' => 'center', 'children' => 'CMS moderno · Laravel + Inertia + Puck'],
                ),
            ],
            [
                'type' => 'HeadingBlock',
                'props' => $this->props(
                    'landing-hero-title',
                    [
                        'level' => 'h1',
                        'align' => 'center',
                        'children' => 'Diseña tus páginas sin tocar código',
                    ],
                ),
            ],
            [
                'type' => 'TextBlock',
                'props' => $this->props(
                    'landing-hero-subtitle',
                    [
                        'content' => '<p style="text-align:center">Una plataforma moderna construida con Laravel, Inertia.js y Puck. Crea landing pages, gestiona medios, organiza menús y más.</p>',
                        'align' => 'center',
                    ],
                ),
            ],
            [
                'type' => 'ButtonBlock',
                'props' => $this->props(
                    'landing-cta-primary',
                    [
                        'text' => 'Ir al dashboard',
                        'url' => '/admin',
                        'variant' => 'primary',
                        'align' => 'center',
                    ],
                ),
            ],
            [
                'type' => 'HeadingBlock',
                'props' => $this->props(
                    'landing-features-title',
                    ['level' => 'h2', 'align' => 'center', 'children' => 'Todo lo que necesitas'],
                ),
            ],
            [
                'type' => 'TextBlock',
                'props' => $this->props(
                    'landing-features-subtitle',
                    [
                        'content' => '<p style="text-align:center">Un CMS completo con editor visual, medios, menús dinámicos y módulos extensibles.</p>',
                        'align' => 'center',
                    ],
                ),
            ],
            [
                'type' => 'ColumnsBlock',
                'props' => $this->props(
                    'landing-features-grid',
                    ['columns' => 3, 'gap' => 'lg'],
                ),
            ],
            [
                'type' => 'ColumnsBlock',
                'props' => $this->props(
                    'landing-cta-row',
                    ['columns' => 2, 'gap' => 'md'],
                ),
            ],
            [
                'type' => 'DividerBlock',
                'props' => $this->props('landing-divider', ['style' => 'solid']),
            ],
            [
                'type' => 'TextBlock',
                'props' => $this->props(
                    'landing-footer-note',
                    [
                        'content' => '<p style="text-align:center; font-size: 0.875rem; color: var(--muted-foreground);">Construido con Laravel 13, Inertia v3, Puck, React 19 y Tailwind 4.</p>',
                        'align' => 'center',
                    ],
                ),
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
            'padding' => ['top' => 24, 'right' => 24, 'bottom' => 24, 'left' => 24],
            'margin' => ['top' => 0, 'bottom' => 0],
            'backgroundColor' => 'transparent',
            'borderRadius' => 'none',
            'boxShadow' => 'none',
            'maxWidth' => '768px',
            'hideOnMobile' => false,
            'hideOnDesktop' => false,
            'animation' => 'none',
            'animationDelay' => 0,
        ];
    }
}
