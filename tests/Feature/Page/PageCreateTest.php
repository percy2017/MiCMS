<?php

use App\Models\Page;

test('guests cannot create pages', function () {
    $this->post(route('admin.paginas.store'), [
        'title' => 'Test',
        'slug' => 'test',
    ])->assertRedirect(route('login'));
});

test('authenticated users can create a page', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->post(route('admin.paginas.store'), [
            'title' => 'My new page',
            'slug' => 'my-new-page',
        ])
        ->assertRedirect();

    expect(Page::query()->where('slug', 'my-new-page')->exists())->toBeTrue();
    expect(Page::query()->first()->status)->toBe(Page::STATUS_DRAFT);
    expect(Page::query()->first()->user_id)->toBe($user->id);
});

test('page title is required', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->from(route('admin.paginas.index'))
        ->post(route('admin.paginas.store'), [
            'title' => '',
            'slug' => 'test',
        ])
        ->assertSessionHasErrors('title');
});

test('page slug must be unique', function () {
    $user = adminUser();
    Page::factory()->create(['slug' => 'taken']);

    $this->actingAs($user)
        ->from(route('admin.paginas.index'))
        ->post(route('admin.paginas.store'), [
            'title' => 'Another',
            'slug' => 'taken',
        ])
        ->assertSessionHasErrors('slug');
});

test('page slug must match the slug format', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->from(route('admin.paginas.index'))
        ->post(route('admin.paginas.store'), [
            'title' => 'Test',
            'slug' => 'Invalid Slug With Spaces',
        ])
        ->assertSessionHasErrors('slug');
});
