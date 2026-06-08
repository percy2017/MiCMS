<?php

use App\Models\Page;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected when trying to set a page as home', function () {
    $page = Page::factory()->create();

    $this->post(route('admin.paginas.set-home', ['page' => $page]))
        ->assertRedirect(route('login'));

    expect($page->fresh()->is_home)->toBeFalse();
});

test('a page can be set as home', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.paginas.set-home', ['page' => $page]))
        ->assertRedirect();

    expect($page->fresh()->is_home)->toBeTrue();
});

test('setting a new page as home removes the flag from the previous one', function () {
    $user = adminUser();
    $oldHome = Page::factory()->create(['is_home' => true]);
    $newHome = Page::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.paginas.set-home', ['page' => $newHome]))
        ->assertRedirect();

    expect($oldHome->fresh()->is_home)->toBeFalse()
        ->and($newHome->fresh()->is_home)->toBeTrue();
});

test('only one page can have is_home true at the database level', function () {
    $page1 = Page::factory()->create(['is_home' => true]);

    expect(fn () => Page::factory()->create(['is_home' => true]))
        ->toThrow(QueryException::class);

    expect($page1->fresh()->is_home)->toBeTrue();
});

test('a page can be unset as home', function () {
    $user = adminUser();
    $page = Page::factory()->create(['is_home' => true]);

    $this->actingAs($user)
        ->delete(route('admin.paginas.unset-home', ['page' => $page]))
        ->assertRedirect();

    expect($page->fresh()->is_home)->toBeFalse();
});

test('deleting the home page clears the is_home flag without affecting others', function () {
    $user = adminUser();
    $home = Page::factory()->create(['is_home' => true]);
    $other = Page::factory()->create();

    $this->actingAs($user)
        ->delete(route('admin.paginas.destroy', ['page' => $home]))
        ->assertRedirect(route('admin.paginas.index'));

    expect(Page::query()->find($home->id))->toBeNull()
        ->and($other->fresh()->is_home)->toBeFalse();
});

test('the home page is rendered at the root url when published', function () {
    $home = Page::factory()->published()->withFixture()->create(['is_home' => true]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $assert) => $assert
            ->component('paginas/show')
            ->where('page.id', $home->id)
            ->where('page.slug', $home->slug),
        );
});

test('a draft home page does not render at the root url, welcome is shown instead', function () {
    Page::factory()->draft()->create(['is_home' => true]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $assert) => $assert
            ->component('welcome'),
        );
});

test('the root url shows welcome when no page is set as home', function () {
    Page::factory()->published()->withFixture()->create();

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $assert) => $assert
            ->component('welcome'),
        );
});

test('the public url helper returns the root for the home page', function () {
    $home = Page::factory()->create(['is_home' => true, 'slug' => 'some-slug']);
    $other = Page::factory()->create(['is_home' => false, 'slug' => 'about']);

    expect($home->publicUrl())->toBe(route('home'))
        ->and($other->publicUrl())->toBe(route('pages.show', ['slug' => 'about']));
});

test('home indicator is exposed in the index response', function () {
    $user = adminUser();
    Page::factory()->create(['is_home' => true, 'title' => 'Mi home']);
    Page::factory()->create(['title' => 'Otra']);

    $this->actingAs($user)
        ->get(route('admin.paginas.index'))
        ->assertInertia(fn (Assert $assert) => $assert
            ->where('pages.data.0.is_home', true)
            ->where('pages.data.1.is_home', false),
        );
});

test('is_home indicator is exposed in the edit response', function () {
    $user = adminUser();
    $page = Page::factory()->create(['is_home' => true]);

    $this->actingAs($user)
        ->get(route('admin.paginas.edit', ['page' => $page]))
        ->assertInertia(fn (Assert $assert) => $assert
            ->where('page.is_home', true),
        );
});
