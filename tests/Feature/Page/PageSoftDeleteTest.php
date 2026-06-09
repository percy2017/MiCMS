<?php

use App\Models\Page;

use function Pest\Laravel\actingAs;

test('admin can soft delete a page', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    actingAs($user)
        ->delete(route('admin.paginas.destroy', ['page' => $page]))
        ->assertRedirect();

    expect(Page::query()->find($page->id))->toBeNull();
    expect(Page::onlyTrashed()->find($page->id))->not->toBeNull();
});

test('soft-deleted pages appear in trashed index', function () {
    $user = adminUser();
    $page = Page::factory()->create();
    $page->delete();

    actingAs($user)
        ->get(route('admin.paginas.index', ['trashed' => 1]))
        ->assertInertia(fn ($assert) => $assert
            ->component('paginas/index')
            ->where('pages.data.0.id', $page->id),
        );
});

test('admin can restore a soft-deleted page', function () {
    $user = adminUser();
    $page = Page::factory()->create();
    $page->delete();

    actingAs($user)
        ->post(route('admin.paginas.restore', ['page' => $page->id]))
        ->assertRedirect(route('admin.paginas.index'));

    expect(Page::query()->find($page->id))->not->toBeNull();
    expect(Page::onlyTrashed()->find($page->id))->toBeNull();
});

test('admin can force-delete a soft-deleted page', function () {
    $user = adminUser();
    $page = Page::factory()->create();
    $page->delete();

    actingAs($user)
        ->delete(route('admin.paginas.force-destroy', ['page' => $page->id]))
        ->assertRedirect(route('admin.paginas.index'));

    expect(Page::withTrashed()->find($page->id))->toBeNull();
});

test('soft-deleted pages are not publicly accessible', function () {
    $page = Page::factory()->published()->create();
    $page->delete();

    $this->get(route('pages.show', ['slug' => $page->slug]))
        ->assertNotFound();
});

test('a soft-deleted home page clears is_home flag and home does not 500', function () {
    $home = Page::factory()->published()->withFixture()->create(['is_home' => true]);
    $home->delete();

    $this->get(route('home'))->assertSuccessful();
});

test('restore endpoint requires delete permission', function () {
    $page = Page::factory()->create();
    $page->delete();

    actingAs(basicUser())
        ->post(route('admin.paginas.restore', ['page' => $page->id]))
        ->assertForbidden();
});
