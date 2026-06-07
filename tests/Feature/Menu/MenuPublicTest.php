<?php

use App\Models\Menu;
use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('menus.locations', ['header' => 'Header', 'footer' => 'Footer']);
});

test('public page response includes menus', function () {
    Page::factory()->create([
        'slug' => 'about',
        'status' => Page::STATUS_PUBLISHED,
    ]);

    $header = Menu::factory()->create(['location' => 'header', 'name' => 'Top nav']);
    $header->items()->create(['label' => 'Inicio', 'url' => '/', 'order' => 0]);
    $header->items()->create(['label' => 'Acerca de', 'url' => '/about', 'order' => 1]);

    $footer = Menu::factory()->create(['location' => 'footer', 'name' => 'Footer']);
    $footer->items()->create(['label' => 'Privacidad', 'url' => '/privacidad', 'order' => 0]);

    $response = $this->get('/about')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('paginas/show')
            ->has('menus.header')
            ->where('menus.header.name', 'Top nav')
            ->has('menus.header.items', 2)
            ->has('menus.footer')
            ->where('menus.footer.name', 'Footer'),
        );
});

test('home page response includes menus', function () {
    $home = Page::factory()->create([
        'slug' => 'home',
        'status' => Page::STATUS_PUBLISHED,
        'is_home' => true,
    ]);

    $header = Menu::factory()->create(['location' => 'header']);
    $header->items()->create(['label' => 'Home', 'url' => '/', 'order' => 0]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('menus.header'),
        );
});

test('page type menu item resolves to page url', function () {
    $page = Page::factory()->create([
        'slug' => 'services',
        'status' => Page::STATUS_PUBLISHED,
        'is_home' => false,
    ]);

    $menu = Menu::factory()->create(['location' => 'header']);
    $menu->items()->create([
        'label' => 'Services',
        'type' => 'page',
        'page_id' => $page->id,
        'order' => 0,
    ]);

    $item = $menu->items()->first();
    expect($item->resolvedUrl())->toBe(route('pages.show', ['slug' => 'services']));
    expect($item->isExternal())->toBeFalse();
});

test('home page menu item resolves to root', function () {
    $page = Page::factory()->create([
        'slug' => 'home',
        'status' => Page::STATUS_PUBLISHED,
        'is_home' => true,
    ]);

    $menu = Menu::factory()->create(['location' => 'header']);
    $menu->items()->create([
        'label' => 'Home',
        'type' => 'page',
        'page_id' => $page->id,
        'order' => 0,
    ]);

    $item = $menu->items()->first();
    expect($item->resolvedUrl())->toBe(route('home'));
});

test('external url is detected', function () {
    $menu = Menu::factory()->create(['location' => 'header']);
    $menu->items()->create([
        'label' => 'GitHub',
        'url' => 'https://github.com/example',
        'target' => '_blank',
        'order' => 0,
    ]);

    $item = $menu->items()->first();
    expect($item->isExternal())->toBeTrue();
    expect($item->resolvedUrl())->toBe('https://github.com/example');
});
