<?php

use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

test('admin can view the permissions matrix', function () {
    actingAs(adminUser())
        ->get(route('admin.permisos.index'))
        ->assertOk();
});

test('admin can create a permission', function () {
    actingAs(adminUser())
        ->post(route('admin.permisos.store'), ['name' => 'do something'])
        ->assertRedirect();

    assertDatabaseHas('permissions', ['name' => 'do something']);
});

test('admin can delete a permission', function () {
    $p = Permission::create(['name' => 'do something', 'guard_name' => 'web']);

    actingAs(adminUser())
        ->delete(route('admin.permisos.destroy', $p))
        ->assertRedirect();

    assertDatabaseMissing('permissions', ['id' => $p->id]);
});

test('a user without view permissions permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('admin.permisos.index'))
        ->assertForbidden();
});
