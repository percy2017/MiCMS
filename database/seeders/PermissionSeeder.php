<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'view pages',
        'create pages',
        'update pages',
        'delete pages',
        'set home pages',
        'view media',
        'create media',
        'update media',
        'delete media',
        'view menus',
        'create menus',
        'update menus',
        'delete menus',
        'create menu items',
        'update menu items',
        'delete menu items',
        'view packages',
        'update packages',
        'toggle packages',
        'view schedule',
        'manage schedule',
        'run schedule',
        'view settings',
        'update settings',
        'view users',
        'create users',
        'update users',
        'delete users',
        'view roles',
        'create roles',
        'update roles',
        'delete roles',
        'view permissions',
        'create permissions',
        'delete permissions',
        'view pos-woo',
        'manage pos-woo',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $this->command?->info('Permissions seedeadas: '.count(self::PERMISSIONS));
    }
}
