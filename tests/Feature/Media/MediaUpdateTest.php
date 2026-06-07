<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('edit page shows the media item', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.media.edit', $media))
        ->assertSuccessful();
});

test('metadata can be updated', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.media.update', $media), [
            'title' => 'A nice photo',
            'alt_text' => 'A photo of a thing',
            'caption' => 'Caption here',
            'description' => 'A long description',
        ])
        ->assertRedirect();

    $media->refresh();

    expect($media->title)->toBe('A nice photo')
        ->and($media->alt_text)->toBe('A photo of a thing')
        ->and($media->caption)->toBe('Caption here')
        ->and($media->description)->toBe('A long description');
});

test('metadata fields are optional', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['title' => 'Original']);

    $this->actingAs($user)
        ->patch(route('admin.media.update', $media), [
            'title' => null,
            'alt_text' => null,
            'caption' => null,
            'description' => null,
        ])
        ->assertRedirect();

    $media->refresh();

    expect($media->title)->toBeNull();
});

test('updating does not touch the file on disk', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['name' => 'photo.jpg']);

    Storage::disk('public')->put(
        'photo.jpg',
        'contents',
    );

    $this->actingAs($user)
        ->patch(route('admin.media.update', $media), ['title' => 'New title'])
        ->assertRedirect();

    Storage::disk('public')->assertExists('photo.jpg');
    expect(Storage::disk('public')->get('photo.jpg'))
        ->toBe('contents');
});

test('guests cannot update', function () {
    $media = Media::factory()->create();

    $this->patch(route('admin.media.update', $media), ['title' => 'Hacked'])
        ->assertRedirect(route('login'));
});
