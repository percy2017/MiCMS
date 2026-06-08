<?php

use App\Models\Page;

test('a page can be deleted', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $this->actingAs($user)
        ->delete(route('admin.paginas.destroy', ['page' => $page]))
        ->assertRedirect(route('admin.paginas.index'));

    expect(Page::query()->find($page->id))->toBeNull();
});

test('a 404 is returned when deleting a non-existent page', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->delete(route('admin.paginas.destroy', ['page' => 999999]))
        ->assertNotFound();
});
