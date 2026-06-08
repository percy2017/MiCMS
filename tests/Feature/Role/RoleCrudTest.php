<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

test('admin can view the roles list', function () {
    actingAs(adminUser())
        ->get(route('admin.roles.index'))
        ->assertOk();
});

test('admin can create a role with permissions', function () {
    actingAs(adminUser())
        ->post(route('admin.roles.store'), [
            'name' => 'reviewer',
            'permissions' => ['view pages'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    assertDatabaseHas('roles', ['name' => 'reviewer']);
    expect(Role::findByName('reviewer', 'web')->hasPermissionTo('view pages'))->toBeTrue();
});

test('admin can update a role', function () {
    $r = Role::create(['name' => 'reviewer', 'guard_name' => 'web']);

    actingAs(adminUser())
        ->patch(route('admin.roles.update', $r), [
            'name' => 'reviewer2',
            'permissions' => ['create pages'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $r->refresh();
    expect($r->name)->toBe('reviewer2');
    expect($r->hasPermissionTo('create pages'))->toBeTrue();
    expect($r->hasPermissionTo('view pages'))->toBeFalse();
});

test('admin cannot edit the admin role', function () {
    $admin = Role::findByName('admin', 'web');

    actingAs(adminUser())
        ->withHeaders(['X-Inertia' => 'true'])
        ->patch(route('admin.roles.update', $admin), [
            'name' => 'super',
            'permissions' => [],
        ])
        ->assertRedirect();
});

test('admin cannot delete a role with users', function () {
    $r = Role::create(['name' => 'temp', 'guard_name' => 'web']);
    $u = User::factory()->create();
    $u->assignRole('temp');

    actingAs(adminUser())
        ->delete(route('admin.roles.destroy', $r))
        ->assertForbidden();

    assertDatabaseHas('roles', ['id' => $r->id]);
});

test('a user without view roles permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});
