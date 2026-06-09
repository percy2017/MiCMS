<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const EDITOR_PERMISSIONS = [
        'view pages',
        'create pages',
        'update pages',
        'set home pages',
        'view media',
        'create media',
        'update media',
        'view menus',
        'create menus',
        'update menus',
        'create menu items',
        'update menu items',
        'delete menu items',
        'view settings',
        'view chatbot',
        'view chats',
        'view chatbot conversations',
        'reply chatbot',
    ];

    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $editor->syncPermissions(self::EDITOR_PERMISSIONS);

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $this->command?->info('Roles seedeados: admin (todos), editor (contenido), user (básico).');
    }
}
