<?php

use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot view the editor', function () {
    $page = Page::factory()->create();

    $this->get(route('admin.paginas.edit', ['page' => $page]))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the editor with the page data', function () {
    $user = adminUser();
    $page = Page::factory()->withFixture()->create();

    $this->actingAs($user)
        ->get(route('admin.paginas.edit', ['page' => $page]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $assert) => $assert
            ->component('paginas/editar')
            ->where('page.id', $page->id)
            ->where('page.title', $page->title)
            ->where('page.slug', $page->slug)
            ->has('page.puck_data'),
        );
});
