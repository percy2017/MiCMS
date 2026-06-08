<?php

use App\Models\Menu;
use App\Models\MenuItem;

test('guests cannot delete menus', function () {
    $menu = Menu::factory()->create();

    $this->delete(route('admin.menus.destroy', ['menu' => $menu->id]))
        ->assertRedirect(route('login'));

    expect(Menu::query()->count())->toBe(1);
});

test('authenticated users can delete a menu', function () {
    $user = adminUser();
    $menu = Menu::factory()->create();

    $this->actingAs($user)
        ->delete(route('admin.menus.destroy', ['menu' => $menu->id]))
        ->assertRedirect(route('admin.menus.index'));

    expect(Menu::query()->count())->toBe(0);
});

test('deleting a menu cascades to items', function () {
    $user = adminUser();
    $menu = Menu::factory()->create();
    $menu->items()->create(['label' => 'X', 'url' => '/', 'order' => 0]);

    $this->actingAs($user)
        ->delete(route('admin.menus.destroy', ['menu' => $menu->id]))
        ->assertRedirect();

    expect(MenuItem::query()->count())->toBe(0);
});
