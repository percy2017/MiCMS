<?php

use App\Models\Page;
use App\Support\HtmlSanitizer;

use function Pest\Laravel\actingAs;

test('script tags in puck_data are stripped on save', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $puck = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => [
                    'id' => 'x',
                    'content' => '<p>Hola</p><script>alert(1)</script>',
                ],
            ],
        ],
    ];

    actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'puck_data' => $puck,
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->puck_data['content'][0]['props']['content'])->not->toContain('<script>');
});

test('heading block children are stored as plain text (no auto wrapping)', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $puck = [
        'content' => [
            [
                'type' => 'HeadingBlock',
                'props' => [
                    'id' => 'h1',
                    'children' => 'Mi título',
                    'level' => 'h1',
                    'align' => 'left',
                ],
            ],
        ],
    ];

    actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'puck_data' => $puck,
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->puck_data['content'][0]['props']['children'])->toBe('Mi título');
});

test('heading block children have html tags stripped', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $puck = [
        'content' => [
            [
                'type' => 'HeadingBlock',
                'props' => [
                    'id' => 'h1',
                    'children' => '<script>alert(1)</script>Hola',
                    'level' => 'h1',
                    'align' => 'left',
                ],
            ],
        ],
    ];

    actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'puck_data' => $puck,
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->puck_data['content'][0]['props']['children'])->toBe('Hola');
    expect($page->puck_data['content'][0]['props']['children'])->not->toContain('<script>');
});

test('javascript: URIs in ButtonBlock url are stripped on save', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $puck = [
        'content' => [
            [
                'type' => 'ButtonBlock',
                'props' => [
                    'id' => 'btn',
                    'text' => 'click',
                    'url' => 'javascript:alert(1)',
                    'variant' => 'primary',
                    'align' => 'center',
                ],
            ],
        ],
    ];

    actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'puck_data' => $puck,
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->puck_data['content'][0]['props']['url'])->toBe('');
});

test('data: URIs in ImageBlock src are stripped on save', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $puck = [
        'content' => [
            [
                'type' => 'ImageBlock',
                'props' => [
                    'id' => 'img',
                    'src' => 'data:image/svg+xml,<script>alert(1)</script>',
                    'alt' => 'x',
                    'caption' => '',
                    'align' => 'center',
                    'rounded' => 'lg',
                    'link_url' => '',
                ],
            ],
        ],
    ];

    actingAs($user)
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'puck_data' => $puck,
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->puck_data['content'][0]['props']['src'])->toBe('');
});

test('disallowed block types are rejected by validation', function () {
    $user = adminUser();
    $page = Page::factory()->create();

    $puck = [
        'content' => [
            [
                'type' => 'EvilHackerBlock',
                'props' => ['id' => 'x'],
            ],
        ],
    ];

    actingAs($user)
        ->from(route('admin.paginas.edit', ['page' => $page]))
        ->patch(route('admin.paginas.update', ['page' => $page]), [
            'puck_data' => $puck,
        ])
        ->assertSessionHasErrors();
});

test('html sanitizer profile keeps safe tags and strips scripts', function () {
    $dirty = '<p>Hola</p><script>alert(1)</script><img src="x.jpg"><a href="javascript:alert(1)">link</a>';
    $clean = HtmlSanitizer::safeHtml($dirty);

    expect($clean)->toContain('<p>Hola</p>');
    expect($clean)->toContain('<img');
    expect($clean)->not->toContain('<script>');
});

test('safe url blocks javascript: scheme', function () {
    expect(HtmlSanitizer::safeUrl('javascript:alert(1)'))->toBe('');
    expect(HtmlSanitizer::safeUrl('data:text/html,foo'))->toBe('');
    expect(HtmlSanitizer::safeUrl('vbscript:msgbox(1)'))->toBe('');
    expect(HtmlSanitizer::safeUrl('https://example.com'))->toBe('https://example.com');
    expect(HtmlSanitizer::safeUrl('/relative'))->toBe('/relative');
});
