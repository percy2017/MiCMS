<?php

use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;

test('a published page is publicly accessible', function () {
    $page = Page::factory()->published()->withFixture()->create();

    $this->get(route('pages.show', ['slug' => $page->slug]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $assert) => $assert
            ->component('paginas/show')
            ->where('page.id', $page->id)
            ->where('page.slug', $page->slug)
            ->has('page.puck_data'),
        );
});

test('a draft page is not publicly accessible', function () {
    $page = Page::factory()->draft()->create();

    $this->get(route('pages.show', ['slug' => $page->slug]))
        ->assertNotFound();
});

test('a non-existent slug returns 404', function () {
    $this->get(route('pages.show', ['slug' => 'does-not-exist']))
        ->assertNotFound();
});

test('admin routes are not treated as public pages', function () {
    $this->get('/admin/paginas')
        ->assertRedirect(route('login'));
});

test('login route is not treated as a public page', function () {
    $this->get('/login')
        ->assertSuccessful();
});
