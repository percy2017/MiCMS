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
                    'content' => [
                        [
                            'type' => 'HeadingBlock',
                            'props' => [
                                'id' => 'landing-hero-title',
                                'level' => 'h1',
                                'align' => 'center',
                                'children' => 'Bienvenido a tu nuevo CMS',
                            ],
                        ],
                        [
                            'type' => 'TextBlock',
                            'props' => [
                                'id' => 'landing-hero-subtitle',
                                'content' => '<p style="text-align:center">Una plataforma moderna construida con Laravel, Inertia y Puck para diseñar tus páginas visualmente.</p>',
                                'align' => 'center',
                            ],
                        ],
                        [
                            'type' => 'SpacerBlock',
                            'props' => [
                                'id' => 'landing-spacer-1',
                                'height' => 24,
                            ],
                        ],
                        [
                            'type' => 'ButtonBlock',
                            'props' => [
                                'id' => 'landing-cta',
                                'text' => 'Ir al dashboard',
                                'url' => '/admin',
                                'variant' => 'primary',
                                'align' => 'center',
                            ],
                        ],
                        [
                            'type' => 'SpacerBlock',
                            'props' => [
                                'id' => 'landing-spacer-2',
                                'height' => 48,
                            ],
                        ],
                        [
                            'type' => 'DividerBlock',
                            'props' => [
                                'id' => 'landing-divider',
                                'style' => 'solid',
                            ],
                        ],
                        [
                            'type' => 'SpacerBlock',
                            'props' => [
                                'id' => 'landing-spacer-3',
                                'height' => 32,
                            ],
                        ],
                        [
                            'type' => 'HeadingBlock',
                            'props' => [
                                'id' => 'landing-features-title',
                                'level' => 'h2',
                                'align' => 'center',
                                'children' => 'Características',
                            ],
                        ],
                        [
                            'type' => 'TextBlock',
                            'props' => [
                                'id' => 'landing-features-text',
                                'content' => '<ul><li>Editor visual Puck</li><li>Biblioteca de medios</li><li>URLs personalizables</li><li>Modo oscuro</li></ul>',
                                'align' => 'center',
                            ],
                        ],
                    ],
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
}
