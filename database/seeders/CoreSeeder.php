<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdminUser();
        $this->seedLandingPage();
        $this->seedDefaultSettings();
    }

    protected function seedAdminUser(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin2026$'),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }
    }

    protected function seedLandingPage(): void
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

    protected function seedDefaultSettings(): void
    {
        Setting::set('site_name', config('app.name'), Setting::TYPE_STRING);
        Setting::set('site_tagline', 'CMS moderno · Laravel + Inertia + Puck', Setting::TYPE_STRING);

        $this->command?->info('Settings por defecto insertadas.');
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
                        'content' => '<p>Una plataforma moderna construida con Laravel, Inertia.js y Puck. Crea landing pages, gestiona medios, organiza menús y más.</p>',
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
                        'content' => '<p>Un CMS completo con editor visual, medios, menús dinámicos y módulos extensibles.</p>',
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
                        'content' => '<p>Construido con Laravel 13, Inertia v3, Puck, React 19 y Tailwind 4.</p>',
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
            'padding' => 'md',
            'marginBottom' => 'none',
            'backgroundColor' => 'transparent',
            'borderRadius' => 'none',
        ];
    }
}
