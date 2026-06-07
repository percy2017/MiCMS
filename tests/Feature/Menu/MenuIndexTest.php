<?php

use App\Models\Menu;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('admin.menus.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can see the menus list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.menus.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('menus/index')
            ->has('menus')
            ->has('locations'),
        );
});

test('menus are listed with item counts', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create(['name' => 'Header menu']);
    $menu->items()->createMany([
        ['label' => 'Home', 'url' => '/', 'order' => 0],
        ['label' => 'About', 'url' => '/about', 'order' => 1],
    ]);

    $this->actingAs($user)
        ->get(route('admin.menus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('menus.0.name', 'Header menu')
            ->where('menus.0.items_count', 2),
        );
});

test('locations config is passed to the view', function () {
    config()->set('menus.locations', [
        'header' => 'Header',
        'footer' => 'Footer',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.menus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locations.header', 'Header')
            ->where('locations.footer', 'Footer'),
        );
});
