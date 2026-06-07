<?php

use App\Models\Page;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('admin.paginas.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can see the pages list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.paginas.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('paginas/index')
            ->has('pages.data')
            ->has('filters'),
        );
});

test('pages are listed paginated', function () {
    $user = User::factory()->create();
    Page::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.paginas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pages.total', 3)
            ->has('pages.data', 3),
        );
});

test('pages can be searched by title', function () {
    $user = User::factory()->create();
    Page::factory()->create(['title' => 'Welcome page']);
    Page::factory()->create(['title' => 'About us']);

    $this->actingAs($user)
        ->get(route('admin.paginas.index', ['search' => 'Welcome']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pages.total', 1)
            ->where('pages.data.0.title', 'Welcome page'),
        );
});

test('pages can be filtered by status', function () {
    $user = User::factory()->create();
    Page::factory()->published()->create();
    Page::factory()->draft()->create();

    $this->actingAs($user)
        ->get(route('admin.paginas.index', ['status' => 'published']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pages.total', 1)
            ->where('pages.data.0.status', 'published'),
        );
});
