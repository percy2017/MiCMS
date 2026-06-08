<?php

use App\Models\Menu;

test('guests cannot create menus', function () {
    $this->post(route('admin.menus.store'), [
        'name' => 'Main',
        'location' => 'header',
    ])->assertRedirect(route('login'));
});

test('authenticated users can create a menu', function () {
    config()->set('menus.locations', [
        'header' => 'Header',
        'footer' => 'Footer',
    ]);
    $user = adminUser();

    $this->actingAs($user)
        ->post(route('admin.menus.store'), [
            'name' => 'Header menu',
            'location' => 'header',
        ])
        ->assertRedirect();

    expect(Menu::query()->where('location', 'header')->exists())->toBeTrue();
    expect(Menu::query()->first()->name)->toBe('Header menu');
});

test('menu name is required', function () {
    config()->set('menus.locations', ['header' => 'Header']);
    $user = adminUser();

    $this->actingAs($user)
        ->from(route('admin.menus.index'))
        ->post(route('admin.menus.store'), [
            'name' => '',
            'location' => 'header',
        ])
        ->assertSessionHasErrors('name');
});

test('menu location must be in registered locations', function () {
    config()->set('menus.locations', ['header' => 'Header']);
    $user = adminUser();

    $this->actingAs($user)
        ->from(route('admin.menus.index'))
        ->post(route('admin.menus.store'), [
            'name' => 'Test',
            'location' => 'sidebar',
        ])
        ->assertSessionHasErrors('location');
});

test('multiple menus can share the same location', function () {
    config()->set('menus.locations', ['header' => 'Header', 'footer' => 'Footer']);
    Menu::factory()->create(['location' => 'header', 'name' => 'First']);
    $user = adminUser();

    $this->actingAs($user)
        ->from(route('admin.menus.index'))
        ->post(route('admin.menus.store'), [
            'name' => 'Second',
            'location' => 'header',
        ])
        ->assertRedirect(route('admin.menus.edit', ['menu' => Menu::query()->where('name', 'Second')->first()->id]));

    expect(Menu::query()->where('location', 'header')->count())->toBe(2);
});
