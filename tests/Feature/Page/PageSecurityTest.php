<?php

use App\Models\Page;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

test('a published page is cached after first request', function () {
    $page = Page::factory()->published()->withFixture()->create();

    $this->get(route('pages.show', ['slug' => $page->slug]))->assertSuccessful();
    $this->get(route('pages.show', ['slug' => $page->slug]))->assertSuccessful();

    expect(Cache::has("page.show.{$page->slug}"))->toBeTrue();
});

test('updating a page invalidates the public cache', function () {
    $user = adminUser();
    $page = Page::factory()->published()->withFixture()->create();

    $this->get(route('pages.show', ['slug' => $page->slug]))->assertSuccessful();
    expect(Cache::has("page.show.{$page->slug}"))->toBeTrue();

    actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'title' => 'Updated title',
        ])
        ->assertRedirect();

    expect(Cache::has("page.show.{$page->slug}"))->toBeFalse();
});

test('soft-deleting a published page invalidates cache and removes it from public view', function () {
    $user = adminUser();
    $page = Page::factory()->published()->withFixture()->create();

    $this->get(route('pages.show', ['slug' => $page->slug]))->assertSuccessful();
    expect(Cache::has("page.show.{$page->slug}"))->toBeTrue();

    actingAs($user)
        ->delete(route('admin.paginas.destroy', ['page' => $page]))
        ->assertRedirect();

    expect(Cache::has("page.show.{$page->slug}"))->toBeFalse();
    $this->get(route('pages.show', ['slug' => $page->slug]))->assertNotFound();
});

test('home page cache is invalidated when home page changes', function () {
    $user = adminUser();
    $oldHome = Page::factory()->published()->withFixture()->create(['is_home' => true]);
    $newHome = Page::factory()->create();

    $this->get(route('home'))->assertSuccessful();
    expect(Cache::has('page.home'))->toBeTrue();

    actingAs($user)
        ->post(route('admin.paginas.set-home', ['page' => $newHome]))
        ->assertRedirect();

    expect(Cache::has('page.home'))->toBeFalse();
});

test('public pages include Cache-Control header', function () {
    $page = Page::factory()->published()->withFixture()->create();

    $response = $this->get(route('pages.show', ['slug' => $page->slug]));
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('public');
    expect($cacheControl)->toContain('max-age=300');
    expect($cacheControl)->toContain('must-revalidate');
});

test('public pages include security headers', function () {
    $page = Page::factory()->published()->withFixture()->create();

    $response = $this->get(route('pages.show', ['slug' => $page->slug]));
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Content-Security-Policy');
});

test('sitemap.xml returns only published pages', function () {
    Page::factory()->published()->create(['title' => 'A', 'slug' => 'page-a']);
    Page::factory()->published()->create(['title' => 'B', 'slug' => 'page-b']);
    Page::factory()->draft()->create(['title' => 'C', 'slug' => 'page-c']);
    Page::factory()->published()->create(['title' => 'D', 'slug' => 'page-d'])->delete();

    $response = $this->get('/sitemap.xml');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    $xml = $response->getContent();
    expect($xml)->toContain('page-a');
    expect($xml)->toContain('page-b');
    expect($xml)->not->toContain('page-c');
    expect($xml)->not->toContain('page-d');
});

test('sitemap.xml includes the home page as priority 1.0', function () {
    $home = Page::factory()->published()->withFixture()->create(['is_home' => true, 'slug' => 'home']);
    Page::factory()->published()->create(['slug' => 'other']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<priority>1.0</priority>');
    expect($xml)->toContain('<priority>0.8</priority>');
});

test('artisan sitemap:generate writes file to public/', function () {
    Page::factory()->published()->create(['slug' => 'one']);
    Page::factory()->published()->create(['slug' => 'two']);

    $this->artisan('sitemap:generate')
        ->assertExitCode(0);

    $path = public_path('sitemap.xml');
    expect(file_exists($path))->toBeTrue();
    $content = file_get_contents($path);
    expect($content)->toContain('one');
    expect($content)->toContain('two');

    unlink($path);
});

test('throttle limiter restricts public page abuse', function () {
    $page = Page::factory()->published()->create(['slug' => 'limited']);

    for ($i = 0; $i < 60; $i++) {
        $this->get(route('pages.show', ['slug' => $page->slug]));
    }

    $response = $this->get(route('pages.show', ['slug' => $page->slug]));
    expect($response->status())->toBeIn([200, 429]);
});
