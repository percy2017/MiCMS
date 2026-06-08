<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

test('admin can view the users list', function () {
    actingAs(adminUser())
        ->get(route('admin.usuarios.index'))
        ->assertOk();
});

test('admin can create a user with a role', function () {
    actingAs(adminUser())
        ->post(route('admin.usuarios.store'), [
            'name' => 'Nuevo',
            'email' => 'nuevo@x.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'roles' => ['editor'],
        ])
        ->assertRedirect(route('admin.usuarios.index'));

    assertDatabaseHas('users', ['email' => 'nuevo@x.com']);
    expect(User::where('email', 'nuevo@x.com')->first()->hasRole('editor'))->toBeTrue();
});

test('admin can update a user and change its roles', function () {
    $u = User::factory()->create(['name' => 'Viejo', 'email' => 'v@x.com']);

    actingAs(adminUser())
        ->patch(route('admin.usuarios.update', $u), [
            'name' => 'Nuevo',
            'email' => 'n@x.com',
            'password' => '',
            'password_confirmation' => '',
            'roles' => ['user'],
        ])
        ->assertRedirect(route('admin.usuarios.index'));

    $u->refresh();
    expect($u->name)->toBe('Nuevo');
    expect($u->email)->toBe('n@x.com');
    expect($u->hasRole('user'))->toBeTrue();
});

test('admin cannot delete themselves', function () {
    $admin = adminUser();

    actingAs($admin)
        ->delete(route('admin.usuarios.destroy', $admin))
        ->assertForbidden();

    assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admin can delete another user', function () {
    $other = User::factory()->create();

    actingAs(adminUser())
        ->delete(route('admin.usuarios.destroy', $other))
        ->assertRedirect(route('admin.usuarios.index'));

    assertDatabaseMissing('users', ['id' => $other->id]);
});

test('a user without view users permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('admin.usuarios.index'))
        ->assertForbidden();
});
