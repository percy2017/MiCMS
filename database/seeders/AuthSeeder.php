<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthSeeder extends Seeder
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
        'view chatbot',
        'view chats',
        'reply chatbot',
        'view chatbot conversations',
        'delete chatbot conversations',
        'update chatbot widget',
        'view quick replies',
        'create quick replies',
        'update quick replies',
        'delete quick replies',
        'view logs',
        'delete logs',
        'view admin',
    ];

    /**
     * @var list<string>
     */
    public const EDITOR_PERMISSIONS = [
        'view pages',
        'create pages',
        'update pages',
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
        'view admin',
        'view chatbot',
        'view chats',
        'view chatbot conversations',
        'reply chatbot',
        'view quick replies',
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedAdminUser();

        $this->command?->info('Auth seedeado: '.count(self::PERMISSIONS).' permisos, 3 roles, 1 admin.');
    }

    protected function seedPermissions(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }

    protected function seedRoles(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $editor->syncPermissions(self::EDITOR_PERMISSIONS);

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
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
}
