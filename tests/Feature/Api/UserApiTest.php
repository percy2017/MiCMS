<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

test('admin can list users via API', function () {
    $admin = adminUser();
    User::factory()->count(2)->create();

    $response = actingAs($admin)
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
        ]);

    expect($response->json('data'))->toHaveCount(4);
});

test('admin can search users via API', function () {
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Bob']);

    actingAs(adminUser())
        ->getJson('/api/v1/users?search=Alice')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Alice')
        ->assertJsonCount(1, 'data');
});

test('admin can show a user via API', function () {
    $user = User::factory()->create(['name' => 'Findable']);

    actingAs(adminUser())
        ->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Findable');
});

test('admin can create a user via API', function () {
    $response = actingAs(adminUser())
        ->postJson('/api/v1/users', [
            'name' => 'Created',
            'email' => 'created@api.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'roles' => ['editor'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Created')
        ->assertJsonPath('data.email', 'created@api.com');

    expect($response->json('data.id'))->toBeInt();
    assertDatabaseHas('users', ['email' => 'created@api.com']);
});

test('admin can update a user via API', function () {
    $user = User::factory()->create(['name' => 'Old', 'email' => 'old@api.com']);

    actingAs(adminUser())
        ->patchJson("/api/v1/users/{$user->id}", [
            'name' => 'New',
            'email' => 'new@api.com',
            'password' => '',
            'password_confirmation' => '',
            'roles' => ['user'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.email', 'new@api.com');

    assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@api.com']);
    assertDatabaseMissing('users', ['email' => 'old@api.com']);
});

test('admin can delete a non-admin user via API', function () {
    $user = User::factory()->create();

    actingAs(adminUser())
        ->deleteJson("/api/v1/users/{$user->id}")
        ->assertNoContent();

    assertDatabaseMissing('users', ['id' => $user->id]);
});

test('admin cannot delete itself via API', function () {
    $admin = adminUser();

    actingAs($admin)
        ->deleteJson("/api/v1/users/{$admin->id}")
        ->assertForbidden();
});

test('admin cannot delete another admin via API', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    actingAs(adminUser())
        ->deleteJson("/api/v1/users/{$otherAdmin->id}")
        ->assertForbidden();
});

test('admin can list available roles via API', function () {
    actingAs(adminUser())
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonStructure([
            '*' => ['id', 'name'],
        ]);
});

test('health endpoint is publicly accessible', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonStructure(['status', 'version', 'timestamp'])
        ->assertJsonPath('status', 'ok');
});

test('unauthenticated user is rejected by user endpoints', function () {
    $this->getJson('/api/v1/users')->assertUnauthorized();
    $this->postJson('/api/v1/users', [])->assertUnauthorized();
});

test('editor cannot list users via API', function () {
    actingAs(editorUser())
        ->getJson('/api/v1/users')
        ->assertForbidden();
});

test('api user creation validates required fields', function () {
    actingAs(adminUser())
        ->postJson('/api/v1/users', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('api user creation rejects duplicate email', function () {
    User::factory()->create(['email' => 'duplicate@api.com']);

    actingAs(adminUser())
        ->postJson('/api/v1/users', [
            'name' => 'Dup',
            'email' => 'duplicate@api.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'roles' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
