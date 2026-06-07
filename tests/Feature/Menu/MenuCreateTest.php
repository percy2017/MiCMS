<?php

use App\Models\Menu;
use App\Models\User;

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
    $user = User::factory()->create();

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
    $user = User::factory()->create();

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
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.menus.index'))
        ->post(route('admin.menus.store'), [
            'name' => 'Test',
            'location' => 'sidebar',
        ])
        ->assertSessionHasErrors('location');
});

test('menu location must be unique', function () {
    config()->set('menus.locations', ['header' => 'Header', 'footer' => 'Footer']);
    Menu::factory()->create(['location' => 'header']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.menus.index'))
        ->post(route('admin.menus.store'), [
            'name' => 'Duplicate',
            'location' => 'header',
        ])
        ->assertSessionHasErrors('location');
});
