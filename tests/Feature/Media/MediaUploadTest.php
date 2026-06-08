<?php

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('guests cannot upload', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    $this->post(route('admin.media.store'), ['file' => $file])
        ->assertRedirect(route('login'));
});

test('authenticated users can upload a file', function () {
    $user = adminUser();
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $this->actingAs($user)
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertRedirect();

    $this->assertDatabaseHas('media', [
        'user_id' => $user->id,
        'name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 800,
        'height' => 600,
    ]);

    Storage::disk('public')->assertExists('photo.jpg');
});

test('files keep their original name', function () {
    $user = adminUser();
    $file = UploadedFile::fake()->create('mi-archivo.pdf', 10, 'application/pdf');

    $this->actingAs($user)
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertRedirect();

    Storage::disk('public')->assertExists('mi-archivo.pdf');
});

test('duplicate filenames get a numeric suffix', function () {
    $user = adminUser();
    $file1 = UploadedFile::fake()->image('photo.jpg');
    $file2 = UploadedFile::fake()->image('photo.jpg');

    $this->actingAs($user)->post(route('admin.media.store'), ['file' => $file1]);
    $this->actingAs($user)->post(route('admin.media.store'), ['file' => $file2]);

    Storage::disk('public')->assertExists('photo.jpg');
    Storage::disk('public')->assertExists('photo-1.jpg');

    expect(Media::count())->toBe(2);
});

test('files exceeding the max size are rejected', function () {
    config()->set('media.max_size', 1024);

    $user = adminUser();
    $file = UploadedFile::fake()->create('big.bin', 5);

    $this->actingAs($user)
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(Media::count())->toBe(0);
});

test('files with blocked extensions are rejected', function () {
    config()->set('media.blocked_extensions', ['php']);

    $user = adminUser();
    $file = UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');

    $this->actingAs($user)
        ->post(route('admin.media.store'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(Media::count())->toBe(0);
});

test('upload requires a file', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->post(route('admin.media.store'), [])
        ->assertSessionHasErrors('file');
});
