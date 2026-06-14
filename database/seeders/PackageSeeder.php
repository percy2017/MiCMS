<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    public const PACKAGES = [
        [
            'slug' => 'pages',
            'name' => 'Pages',
            'menu_label' => 'Páginas',
            'version' => '1.0.0',
            'description' => 'Editor visual Puck + páginas dinámicas con slugs.',
            'category' => 'content',
            'icon' => 'FileText',
            'enabled' => true,
        ],
        [
            'slug' => 'media',
            'name' => 'Media',
            'menu_label' => 'Medios',
            'version' => '1.0.0',
            'description' => 'Biblioteca de imágenes, video, audio y documentos.',
            'category' => 'content',
            'icon' => 'Image',
            'enabled' => true,
        ],
        [
            'slug' => 'menus',
            'name' => 'Menus',
            'menu_label' => 'Menús',
            'version' => '1.0.0',
            'description' => 'Menús dinámicos con items anidados y múltiples ubicaciones.',
            'category' => 'content',
            'icon' => 'Menu',
            'enabled' => true,
        ],
        [
            'slug' => 'settings',
            'name' => 'Settings',
            'menu_label' => 'Configuración',
            'version' => '1.0.0',
            'description' => 'Configuración global del sitio (KV store tipado).',
            'category' => 'system',
            'icon' => 'Settings',
            'enabled' => true,
        ],
        [
            'slug' => 'users',
            'name' => 'Users & Roles',
            'menu_label' => 'Usuarios y Roles',
            'version' => '1.0.0',
            'description' => 'Gestión de usuarios, roles y permisos (Spatie).',
            'category' => 'system',
            'icon' => 'Users',
            'enabled' => true,
        ],
        [
            'slug' => 'schedule',
            'name' => 'Scheduled Tasks',
            'menu_label' => 'Tareas programadas',
            'version' => '1.0.0',
            'description' => 'Tareas programadas tipo cron con logs y overlap.',
            'category' => 'system',
            'icon' => 'Clock',
            'enabled' => true,
        ],
        [
            'slug' => 'agent',
            'name' => 'AI Agent',
            'menu_label' => 'Agente IA',
            'version' => '1.0.0',
            'description' => 'Agente IA con conversaciones, herramientas y memoria.',
            'category' => 'ai',
            'icon' => 'Bot',
            'enabled' => true,
        ],
        [
            'slug' => 'chatbot',
            'name' => 'ChatBot',
            'menu_label' => 'ChatBot',
            'version' => '1.0.0',
            'description' => 'Multi-canal: widget web + Evolution API (WhatsApp).',
            'category' => 'communication',
            'icon' => 'MessageCircle',
            'enabled' => true,
        ],
        [
            'slug' => 'pos-woo',
            'name' => 'POS WooCommerce',
            'menu_label' => 'POS WooCommerce',
            'version' => '1.0.0',
            'description' => 'Punto de venta integrado con WooCommerce.',
            'category' => 'commerce',
            'icon' => 'ShoppingCart',
            'enabled' => true,
        ],
    ];

    public function run(): void
    {
        foreach (self::PACKAGES as $row) {
            Package::updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['installed' => true],
            );
        }

        $this->command?->info('Packages seedeados: '.count(self::PACKAGES).' módulos del sistema.');
    }
}
