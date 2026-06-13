<?php

use App\Jobs\FetchLinkPreviewsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

test('refetch-failed finds messages with error media_preview and dispatches job', function (): void {
    Bus::fake();

    $conv = Conversation::factory()->create();
    Message::create([
        'conversation_id' => $conv->id,
        'external_id' => 'm1',
        'role' => 'user',
        'type' => 'text',
        'content' => 'https://example.com',
        'metadata' => [
            'media_kind' => 'link',
            'media_preview' => [
                'url' => 'https://example.com',
                'title' => null,
                'description' => null,
                'image' => null,
                'error' => 'script_failed',
            ],
        ],
    ]);

    $this->artisan('link-previews:refetch-failed')
        ->expectsOutputToContain('Re-despachados: 1')
        ->assertSuccessful();

    Bus::assertDispatched(FetchLinkPreviewsJob::class);

    $message = Message::first();
    expect($message->metadata['media_preview'] ?? null)->toBeNull();
});

test('refetch-failed ignores messages with valid media_preview', function (): void {
    Bus::fake();

    $conv = Conversation::factory()->create();
    Message::create([
        'conversation_id' => $conv->id,
        'external_id' => 'm2',
        'role' => 'user',
        'type' => 'text',
        'content' => 'https://example.com',
        'metadata' => [
            'media_kind' => 'link',
            'media_preview' => [
                'url' => 'https://example.com',
                'title' => 'Example',
                'description' => 'An example',
                'image' => 'https://example.com/og.png',
                'error' => null,
            ],
        ],
    ]);

    $this->artisan('link-previews:refetch-failed')
        ->expectsOutputToContain('No se encontraron')
        ->assertSuccessful();

    Bus::assertNotDispatched(FetchLinkPreviewsJob::class);
});

test('refetch-failed with --purge-cache purges cache entries', function (): void {
    Cache::put(
        'link_preview:'.md5('https://example.com/old-error'),
        ['error' => 'script_failed', 'url' => 'https://example.com/old-error'],
        60,
    );

    Bus::fake();
    $conv = Conversation::factory()->create();
    Message::create([
        'conversation_id' => $conv->id,
        'external_id' => 'm3',
        'role' => 'user',
        'type' => 'text',
        'content' => 'https://example.com/old-error',
        'metadata' => [
            'media_kind' => 'link',
            'media_preview' => [
                'url' => 'https://example.com/old-error',
                'error' => 'script_failed',
            ],
        ],
    ]);

    $this->artisan('link-previews:refetch-failed', ['--purge-cache' => true])
        ->expectsOutputToContain('Entradas de cache purgadas: 1')
        ->assertSuccessful();

    expect(Cache::has('link_preview:'.md5('https://example.com/old-error')))->toBeFalse();
});

test('refetch-failed with --message option targets single message', function (): void {
    Bus::fake();

    $conv = Conversation::factory()->create();
    $target = Message::create([
        'conversation_id' => $conv->id,
        'external_id' => 'm4',
        'role' => 'user',
        'type' => 'text',
        'content' => 'https://example.com/a',
        'metadata' => [
            'media_kind' => 'link',
            'media_preview' => ['error' => 'x', 'url' => 'https://example.com/a'],
        ],
    ]);
    Message::create([
        'conversation_id' => $conv->id,
        'external_id' => 'm5',
        'role' => 'user',
        'type' => 'text',
        'content' => 'https://example.com/b',
        'metadata' => [
            'media_kind' => 'link',
            'media_preview' => ['error' => 'x', 'url' => 'https://example.com/b'],
        ],
    ]);

    $this->artisan('link-previews:refetch-failed', ['--message' => $target->id])
        ->expectsOutputToContain('Re-despachados: 1')
        ->assertSuccessful();

    Bus::assertDispatchedTimes(FetchLinkPreviewsJob::class, 1);
});
