<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.jpg';

        return [
            'user_id' => User::factory(),
            'disk' => 'public',
            'path' => $name,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 1_000_000),
            'name' => $name,
            'title' => fake()->optional(0.5)->words(3, true),
            'alt_text' => fake()->optional(0.3)->sentence(),
            'caption' => null,
            'description' => null,
            'width' => fake()->numberBetween(400, 3000),
            'height' => fake()->numberBetween(400, 3000),
        ];
    }

    /**
     * Configure the model to write a real dummy file to the given disk.
     * Useful for tests that exercise download/streaming.
     */
    public function withRealFile(string $disk = 'public'): static
    {
        return $this->afterCreating(function (Media $media) use ($disk) {
            $file = UploadedFile::fake()->image($media->name);
            Storage::disk($disk)->putFileAs(
                dirname($media->path),
                $file,
                basename($media->path),
            );
        });
    }
}
