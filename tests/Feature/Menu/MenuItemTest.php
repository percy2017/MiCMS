<?php

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    config()->set('menus.locations', ['header' => 'Header', 'footer' => 'Footer']);
});

test('can add a custom link to a menu', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.menus.items.store', ['menu' => $menu->id]), [
            'type' => 'custom',
            'label' => 'About',
            'url' => '/about',
            'target' => '_self',
        ])
        ->assertRedirect();

    expect($menu->items()->where('label', 'About')->where('url', '/about')->exists())->toBeTrue();
});

test('can add a page link to a menu', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();
    $page = Page::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.menus.items.store', ['menu' => $menu->id]), [
            'type' => 'page',
            'label' => 'Home',
            'page_id' => $page->id,
            'target' => '_self',
        ])
        ->assertRedirect();

    $item = $menu->items()->where('label', 'Home')->first();
    expect($item)->not->toBeNull();
    expect($item->type)->toBe('page');
    expect($item->page_id)->toBe($page->id);
});

test('custom link requires a url', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.menus.edit', ['menu' => $menu->id]))
        ->post(route('admin.menus.items.store', ['menu' => $menu->id]), [
            'type' => 'custom',
            'label' => 'No url',
            'url' => '',
            'target' => '_self',
        ])
        ->assertSessionHasErrors('url');
});

test('page type requires a page_id', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.menus.edit', ['menu' => $menu->id]))
        ->post(route('admin.menus.items.store', ['menu' => $menu->id]), [
            'type' => 'page',
            'label' => 'No page',
            'target' => '_self',
        ])
        ->assertSessionHasErrors('page_id');
});

test('parent_id must belong to the same menu', function () {
    $user = User::factory()->create();
    $menuA = Menu::factory()->create();
    $menuB = Menu::factory()->create();
    $otherItem = MenuItem::factory()->create(['menu_id' => $menuB->id]);

    $this->actingAs($user)
        ->from(route('admin.menus.edit', ['menu' => $menuA->id]))
        ->post(route('admin.menus.items.store', ['menu' => $menuA->id]), [
            'type' => 'custom',
            'label' => 'Child',
            'url' => '/x',
            'target' => '_self',
            'parent_id' => $otherItem->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('can update an item', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();
    $item = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Old']);

    $this->actingAs($user)
        ->patch(route('admin.menus.items.update', ['menu' => $menu->id, 'item' => $item->id]), [
            'label' => 'New label',
            'url' => '/new',
            'type' => 'custom',
            'target' => '_blank',
        ])
        ->assertRedirect();

    expect($item->fresh()->label)->toBe('New label');
    expect($item->fresh()->target)->toBe('_blank');
});

test('cannot update an item from a different menu', function () {
    $user = User::factory()->create();
    $menuA = Menu::factory()->create();
    $menuB = Menu::factory()->create();
    $item = MenuItem::factory()->create(['menu_id' => $menuB->id]);

    $this->actingAs($user)
        ->patch(route('admin.menus.items.update', ['menu' => $menuA->id, 'item' => $item->id]), [
            'label' => 'Hacked',
        ])
        ->assertNotFound();
});

test('can delete an item', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();
    $item = MenuItem::factory()->create(['menu_id' => $menu->id]);

    $this->actingAs($user)
        ->delete(route('admin.menus.items.destroy', ['menu' => $menu->id, 'item' => $item->id]))
        ->assertRedirect();

    expect(MenuItem::find($item->id))->toBeNull();
});

test('deleting a parent promotes children to root', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();
    $parent = MenuItem::factory()->create(['menu_id' => $menu->id]);
    $child = MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'parent_id' => $parent->id,
    ]);

    $this->actingAs($user)
        ->delete(route('admin.menus.items.destroy', ['menu' => $menu->id, 'item' => $parent->id]))
        ->assertRedirect();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('can reorder items', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();
    $a = MenuItem::factory()->create(['menu_id' => $menu->id, 'order' => 0, 'label' => 'A']);
    $b = MenuItem::factory()->create(['menu_id' => $menu->id, 'order' => 1, 'label' => 'B']);
    $c = MenuItem::factory()->create(['menu_id' => $menu->id, 'order' => 2, 'label' => 'C']);

    $this->actingAs($user)
        ->post(route('admin.menus.items.reorder', ['menu' => $menu->id]), [
            'items' => [
                ['id' => $c->id, 'parent_id' => null, 'order' => 0],
                ['id' => $a->id, 'parent_id' => null, 'order' => 1],
                ['id' => $b->id, 'parent_id' => null, 'order' => 2],
            ],
        ])
        ->assertRedirect();

    expect($c->fresh()->order)->toBe(0);
    expect($a->fresh()->order)->toBe(1);
    expect($b->fresh()->order)->toBe(2);
});

test('reorder can change parent_id', function () {
    $user = User::factory()->create();
    $menu = Menu::factory()->create();
    $parent = MenuItem::factory()->create(['menu_id' => $menu->id, 'order' => 0]);
    $child = MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'order' => 1]);

    $this->actingAs($user)
        ->post(route('admin.menus.items.reorder', ['menu' => $menu->id]), [
            'items' => [
                ['id' => $parent->id, 'parent_id' => null, 'order' => 0],
                ['id' => $child->id, 'parent_id' => null, 'order' => 1],
            ],
        ])
        ->assertRedirect();

    expect($child->fresh()->parent_id)->toBeNull();
});
