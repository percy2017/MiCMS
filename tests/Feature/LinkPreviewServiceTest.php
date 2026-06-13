<?php

use App\Services\LinkPreviewService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->service = app(LinkPreviewService::class);
});

test('extractUrls returns empty array for empty text', function (): void {
    expect($this->service->extractUrls(''))->toBe([]);
});

test('extractUrls finds http and https URLs', function (): void {
    $urls = $this->service->extractUrls('Mira https://example.com y http://test.org/foo');
    expect($urls)->toBe(['https://example.com', 'http://test.org/foo']);
});

test('extractUrls deduplicates URLs', function (): void {
    $urls = $this->service->extractUrls('https://example.com https://example.com https://example.com');
    expect($urls)->toBe(['https://example.com']);
});

test('extractUrls strips trailing punctuation', function (): void {
    $urls = $this->service->extractUrls('Ve https://example.com. Y https://test.org, también https://foo.bar!');
    expect($urls)->toBe(['https://example.com', 'https://test.org', 'https://foo.bar']);
});

test('extractUrls ignores non-http protocols', function (): void {
    $urls = $this->service->extractUrls('Ver ftp://server.com y mailto:a@b.com y https://ok.com');
    expect($urls)->toBe(['https://ok.com']);
});

test('extractUrls limits to 5 URLs per message', function (): void {
    $text = implode(' ', array_map(fn (int $i) => "https://x.com/{$i}", range(1, 10)));
    $urls = $this->service->extractUrls($text);
    expect($urls)->toHaveCount(5);
});

test('fetchOne returns empty item for invalid URL', function (): void {
    $item = $this->service->fetchOne('not-a-url');
    expect($item['error'])->toBe('invalid_url');
    expect($item['url'])->toBe('not-a-url');
});

test('fetchOne blocks SSRF to private IPs (127.0.0.1)', function (): void {
    $item = $this->service->fetchOne('http://127.0.0.1/admin');
    expect($item['error'])->toBe('ssrf_blocked');
});

test('fetchOne blocks SSRF to private IPs (10.x)', function (): void {
    $item = $this->service->fetchOne('http://10.0.0.1/admin');
    expect($item['error'])->toBe('ssrf_blocked');
});

test('fetchOne blocks SSRF to localhost', function (): void {
    $item = $this->service->fetchOne('http://localhost/');
    expect($item['error'])->toBe('ssrf_blocked');
});

test('fetchOne caches result for the same URL', function (): void {
    $cached = [
        'url' => 'https://example.com/cached',
        'title' => 'Cached Title',
        'description' => 'Cached Description',
        'image' => null,
        'image_width' => null,
        'image_height' => null,
        'site_name' => 'Example',
        'favicon' => null,
        'final_url' => 'https://example.com/cached',
        'error' => null,
    ];
    Cache::put('link_preview:'.md5('https://example.com/cached'), $cached, 60);
    $item = $this->service->fetchOne('https://example.com/cached');
    expect($item['title'])->toBe('Cached Title');
});

test('fetchOne does not cache error items for 7 days', function (): void {
    $errorItem = [
        'url' => 'https://example.com/error',
        'title' => null,
        'description' => null,
        'image' => null,
        'site_name' => null,
        'favicon' => null,
        'final_url' => 'https://example.com/error',
        'error' => 'script_failed',
    ];

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('isErrorItem');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $errorItem))->toBeTrue();
});

test('fetchOne treats empty items (no title/description/image) as errors', function (): void {
    $emptyItem = [
        'url' => 'https://example.com/empty',
        'title' => null,
        'description' => null,
        'image' => null,
        'site_name' => null,
        'favicon' => null,
        'final_url' => 'https://example.com/empty',
        'error' => null,
    ];

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('isErrorItem');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $emptyItem))->toBeTrue();
});

test('fetchOne treats valid items (with title) as non-errors', function (): void {
    $validItem = [
        'url' => 'https://example.com/ok',
        'title' => 'A real title',
        'description' => null,
        'image' => null,
        'error' => null,
    ];

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('isErrorItem');
    $method->setAccessible(true);

    expect($method->invoke($this->service, $validItem))->toBeFalse();
});
