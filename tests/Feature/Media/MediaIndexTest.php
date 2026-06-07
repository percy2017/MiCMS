<?php

use App\Models\Media;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('admin.media.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can see the media library', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.media.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('media/index')
            ->has('media.data')
            ->has('filters'),
        );
});

test('library lists media paginated', function () {
    $user = User::factory()->create();
    Media::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.media.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('media/index')
            ->where('media.total', 3)
            ->has('media.data', 3),
        );
});

test('library can be searched by name', function () {
    $user = User::factory()->create();
    Media::factory()->create(['name' => 'vacation.jpg']);
    Media::factory()->create(['name' => 'invoice.pdf']);

    $this->actingAs($user)
        ->get(route('admin.media.index', ['search' => 'vacation']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('media.total', 1)
            ->where('media.data.0.name', 'vacation.jpg'),
        );
});

test('library can be filtered by mime type', function () {
    $user = User::factory()->create();
    Media::factory()->create(['mime_type' => 'image/jpeg']);
    Media::factory()->create(['mime_type' => 'application/pdf']);

    $this->actingAs($user)
        ->get(route('admin.media.index', ['type' => 'image']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('media.total', 1)
            ->where('media.data.0.mime_type', 'image/jpeg'),
        );
});

test('library returns json with proper structure for the media picker modal', function () {
    $user = User::factory()->create();
    Media::factory()->create(['mime_type' => 'image/jpeg', 'name' => 'sample.jpg']);
    Media::factory()->create(['mime_type' => 'video/mp4', 'name' => 'clip.mp4']);

    $response = $this->actingAs($user)
        ->getJson(route('admin.media.index', ['type' => 'image', 'per_page' => 60]));

    $response
        ->assertSuccessful()
        ->assertJsonStructure([
            'media' => [
                'data' => [
                    '*' => ['id', 'name', 'url', 'mime_type', 'is_image', 'is_video'],
                ],
                'current_page',
                'last_page',
            ],
            'filters' => ['search', 'type'],
            'max_size',
        ])
        ->assertJsonPath('media.data.0.mime_type', 'image/jpeg')
        ->assertJsonCount(1, 'media.data');
});
