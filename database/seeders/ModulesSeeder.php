<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'slug' => 'pos-woo',
                'name' => 'POS WooCommerce',
                'menu_label' => 'Pos Woo',
                'version' => '1.0.0',
                'description' => 'Punto de venta conectado a WooCommerce para cobros, facturación y gestión de pedidos en tienda.',
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