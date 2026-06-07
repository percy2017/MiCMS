<?php

use App\Models\Page;
use App\Models\User;

test('puck_data can be updated', function () {
    $user = User::factory()->create();
    $page = Page::factory()->create();
    $newData = [
        'content' => [
            [
                'type' => 'HeadingBlock',
                'props' => ['id' => 'x1', 'children' => 'Hello', 'level' => 'h1', 'align' => 'left'],
            ],
        ],
        'root' => ['props' => ['title' => 'Test']],
        'zones' => [],
    ];

    $this->actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'puck_data' => $newData,
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->puck_data)->toBe($newData);
});

test('status can be changed to published', function () {
    $user = User::factory()->create();
    $page = Page::factory()->draft()->create();

    expect($page->status)->toBe(Page::STATUS_DRAFT);
    expect($page->published_at)->toBeNull();

    $this->actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'status' => Page::STATUS_PUBLISHED,
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->status)->toBe(Page::STATUS_PUBLISHED);
    expect($page->published_at)->not->toBeNull();
});

test('publishing a page that was already published does not reset published_at', function () {
    $user = User::factory()->create();
    $page = Page::factory()->published()->create();
    $original = $page->published_at;

    $this->actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'status' => Page::STATUS_PUBLISHED,
        ]);

    $page->refresh();
    expect($page->published_at->toIso8601String())->toBe($original->toIso8601String());
});

test('reverting to draft clears published_at', function () {
    $user = User::factory()->create();
    $page = Page::factory()->published()->create();

    $this->actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'status' => Page::STATUS_DRAFT,
        ]);

    $page->refresh();
    expect($page->status)->toBe(Page::STATUS_DRAFT);
    expect($page->published_at)->toBeNull();
});

test('slug can be updated and must remain unique', function () {
    $user = User::factory()->create();
    $page = Page::factory()->create(['slug' => 'old']);
    Page::factory()->create(['slug' => 'taken']);

    $this->actingAs($user)
        ->from(route('admin.paginas.edit', ['page' => $page]))
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'slug' => 'new',
        ])
        ->assertSessionHasNoErrors();

    $page->refresh();
    expect($page->slug)->toBe('new');

    $this->actingAs($user)
        ->from(route('admin.paginas.edit', ['page' => $page]))
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'slug' => 'taken',
        ])
        ->assertSessionHasErrors('slug');
});

test('invalid status is rejected', function () {
    $user = User::factory()->create();
    $page = Page::factory()->create();

    $this->actingAs($user)
        ->from(route('admin.paginas.edit', ['page' => $page]))
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors('status');
});
