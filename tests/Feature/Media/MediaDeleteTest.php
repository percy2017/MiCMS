<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('guests cannot delete', function () {
    $media = Media::factory()->create();

    $this->delete(route('admin.media.destroy', $media))
        ->assertRedirect(route('login'));
});

test('deleting removes the file and the record', function () {
    $user = User::factory()->create();
    Storage::disk('public')->put('photo.jpg', 'contents');
    $media = Media::factory()->create([
        'disk' => 'public',
        'path' => 'photo.jpg',
        'name' => 'photo.jpg',
    ]);

    $this->actingAs($user)
        ->delete(route('admin.media.destroy', $media))
        ->assertRedirect(route('admin.media.index'));

    Storage::disk('public')->assertMissing('photo.jpg');
    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

test('deleting a record whose file is already missing does not error', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'disk' => 'public',
        'path' => 'ghost.jpg',
        'name' => 'ghost.jpg',
    ]);

    $this->actingAs($user)
        ->delete(route('admin.media.destroy', $media))
        ->assertRedirect(route('admin.media.index'));

    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

test('uploading a file with a previously deleted name reuses the slot', function () {
    $user = User::factory()->create();

    $first = UploadedFile::fake()->image('photo.jpg');
    $this->actingAs($user)->post(route('admin.media.store'), ['file' => $first]);

    $media = Media::latest('id')->first();
    $this->actingAs($user)->delete(route('admin.media.destroy', $media));

    $second = UploadedFile::fake()->image('photo.jpg');
    $this->actingAs($user)->post(route('admin.media.store'), ['file' => $second]);

    Storage::disk('public')->assertExists('photo.jpg');
    expect(Media::count())->toBe(1);
});
