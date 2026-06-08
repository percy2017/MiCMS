<?php

use App\Models\Menu;
use App\Models\User;

test('guests cannot update menus', function () {
    $menu = Menu::factory()->create(['name' => 'Old name']);

    $this->patch(route('admin.menus.update', ['menu' => $menu->id]), [
        'name' => 'New name',
    ])->assertRedirect(route('login'));

    expect($menu->fresh()->name)->toBe('Old name');
});

test('authenticated users can update a menu', function () {
    config()->set('menus.locations', ['header' => 'Header', 'footer' => 'Footer']);
    $user = User::factory()->create();
    $menu = Menu::factory()->create(['name' => 'Old', 'location' => 'header']);

    $this->actingAs($user)
        ->patch(route('admin.menus.update', ['menu' => $menu->id]), [
            'name' => 'New name',
            'location' => 'footer',
        ])
        ->assertRedirect();

    $fresh = $menu->fresh();
    expect($fresh->name)->toBe('New name');
    expect($fresh->location)->toBe('footer');
});

test('menu name is required on update', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.menus.edit', ['menu' => $menu->id]))
        ->patch(route('admin.menus.update', ['menu' => $menu->id]), [
            'name' => '',
        ])
        ->assertSessionHasErrors('name');
});

test('updating to a location already used by another menu is allowed', function () {
    config()->set('menus.locations', ['header' => 'Header', 'footer' => 'Footer']);
    Menu::factory()->create(['location' => 'footer', 'name' => 'Footer 1']);
    $menu = Menu::factory()->create(['location' => 'header', 'name' => 'Header 1']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.menus.edit', ['menu' => $menu->id]))
        ->patch(route('admin.menus.update', ['menu' => $menu->id]), [
            'name' => 'Header 1 Renamed',
            'location' => 'footer',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.menus.edit', ['menu' => $menu->id]));

    expect(Menu::query()->where('location', 'footer')->count())->toBe(2);
});
