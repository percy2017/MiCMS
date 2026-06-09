<?php

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

test('admin can access the API documentation UI', function () {
    actingAs(adminUser())
        ->get('/docs/api')
        ->assertOk();
});

test('editor is forbidden from accessing the API documentation UI', function () {
    actingAs(editorUser())
        ->get('/docs/api')
        ->assertForbidden();
});

test('basic user is forbidden from accessing the API documentation UI', function () {
    actingAs(basicUser())
        ->get('/docs/api')
        ->assertForbidden();
});

test('guest is redirected to login when accessing the API documentation UI', function () {
    $this->get('/docs/api')
        ->assertRedirect();
});

test('admin can fetch the OpenAPI JSON spec', function () {
    $response = actingAs(adminUser())
        ->getJson('/docs/api.json')
        ->assertOk();

    $response->assertJsonStructure([
        'openapi',
        'info' => ['title', 'version'],
        'paths' => [
            '/v1/users',
            '/v1/users/{user}',
        ],
    ]);

    expect($response->json('openapi'))->toStartWith('3.');
    expect($response->json('paths./v1/users.get.summary'))->toBe('List users');
});

test('guest cannot fetch the OpenAPI JSON spec', function () {
    getJson('/docs/api.json')->assertRedirect();
});
