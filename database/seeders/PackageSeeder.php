<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'slug' => 'chat-widget',
                'name' => 'Chat Widget',
                'menu_label' => 'Chat',
                'version' => '1.0.0',
                'description' => 'Botón flotante de chat en el frontend (/) para atender visitantes en tiempo real.',
                'author' => 'CMS Team',
                'category' => Package::CATEGORY_COMMUNICATION,
                'icon' => 'MessageCircle',
                'enabled' => false,
                'installed' => true,
            ],
            [
                'slug' => 'crm',
                'name' => 'CRM',
                'menu_label' => 'CRM',
                'version' => '1.0.0',
                'description' => 'Gestión de clientes, leads y pipeline de ventas para tu equipo comercial.',
                'author' => 'CMS Team',
                'category' => Package::CATEGORY_BUSINESS,
                'icon' => 'Users',
                'enabled' => false,
                'installed' => true,
            ],
            [
                'slug' => 'tvp-pos',
                'name' => 'TVP / POS',
                'menu_label' => 'TVP / POS',
                'version' => '1.0.0',
                'description' => 'Punto de venta para cobros, facturación y control de inventario en tienda.',
                'author' => 'CMS Team',
                'category' => Package::CATEGORY_BUSINESS,
                'icon' => 'ShoppingCart',
                'enabled' => false,
                'installed' => true,
            ],
        ];

        foreach ($packages as $data) {
            Package::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
