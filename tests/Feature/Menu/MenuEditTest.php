<?php

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $menu = Menu::factory()->create();

    $this->get(route('admin.menus.edit', ['menu' => $menu->id]))
        ->assertRedirect(route('login'));
});

test('authenticated users can edit a menu', function () {
    $user = adminUser();
    $menu = Menu::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.menus.edit', ['menu' => $menu->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('menus/editar')
            ->where('menu.id', $menu->id)
            ->has('menu.items')
            ->has('locations')
            ->has('pages'),
        );
});

test('menu items are nested in the response', function () {
    $user = adminUser();
    $menu = Menu::factory()->create();
    $parent = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Parent']);
    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'parent_id' => $parent->id,
        'label' => 'Child',
    ]);

    $this->actingAs($user)
        ->get(route('admin.menus.edit', ['menu' => $menu->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('menu.items', 1)
            ->where('menu.items.0.label', 'Parent')
            ->has('menu.items.0.children', 1)
            ->where('menu.items.0.children.0.label', 'Child'),
        );
});

test('pages are available for page-type items', function () {
    $user = adminUser();
    $menu = Menu::factory()->create();
    Page::factory()->create(['title' => 'About page', 'status' => Page::STATUS_PUBLISHED]);

    $this->actingAs($user)
        ->get(route('admin.menus.edit', ['menu' => $menu->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pages.0.title', 'About page'),
        );
});
