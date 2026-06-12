<?php

use App\Models\Media;
use App\Models\User;

test('search customers returns empty array for queries shorter than 4 chars', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=76');

    $response->assertOk()
        ->assertJson(['data' => []]);
});

test('search customers finds user by partial phone match', function () {
    $admin = adminUser();
    User::factory()->create([
        'name' => 'Cliente Telefono',
        'email' => 'telefono@example.com',
        'phone' => '59176939617',
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=7693');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['phone'])->toBe('59176939617');
    expect($data[0]['name'])->toBe('Cliente Telefono');
});

test('search customers finds user by name', function () {
    $admin = adminUser();
    User::factory()->create([
        'name' => 'Juana Perez',
        'email' => 'juana@example.com',
        'phone' => '59111111111',
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=Juana');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('Juana Perez');
});

test('search customers finds user by email', function () {
    $admin = adminUser();
    User::factory()->create([
        'name' => 'Carlos Lopez',
        'email' => 'carlos.lopez@correo.com',
        'phone' => '59122222222',
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=carlos');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['email'])->toBe('carlos.lopez@correo.com');
});

test('search customers excludes users without phone', function () {
    $admin = adminUser();
    User::factory()->create([
        'name' => 'Sin Telefono',
        'email' => 'sintelefono@example.com',
        'phone' => null,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=sintelefono');

    $response->assertOk()
        ->assertJson(['data' => []]);
});

test('search customers prioritizes phone prefix matches over name and email', function () {
    $admin = adminUser();
    User::factory()->create([
        'name' => 'Juan Telefono',
        'email' => 'juan@example.com',
        'phone' => '59176930000',
    ]);
    User::factory()->create([
        'name' => 'Otro Sin 7693',
        'email' => 'otro@example.com',
        'phone' => '59111111111',
    ]);
    User::factory()->create([
        'name' => 'Maria Telefono',
        'email' => 'maria@example.com',
        'phone' => '59199999999',
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=telefono');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect($data[0]['phone'])->toBe('59176930000');
    expect($data[1]['phone'])->toBe('59199999999');
});

test('search customers includes avatar url when user has avatar media', function () {
    $admin = adminUser();
    $media = Media::factory()->create([
        'disk' => 'public',
        'path' => 'avatars/test.jpg',
    ]);
    User::factory()->create([
        'name' => 'Con Avatar',
        'email' => 'avatar@example.com',
        'phone' => '59133333333',
        'avatar_media_id' => $media->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=avatar');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['avatar_url'])->toContain('avatars/test.jpg');
});

test('search customers returns null avatar_url when user has no avatar media', function () {
    $admin = adminUser();
    User::factory()->create([
        'name' => 'Sin Avatar',
        'email' => 'sinavatar@example.com',
        'phone' => '59144444444',
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/pos-woo/customers?search=sinavatar');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['avatar_url'])->toBeNull();
});

test('search customers requires view pos-woo permission', function () {
    $basic = basicUser();

    $response = $this->actingAs($basic)->getJson('/admin/pos-woo/customers?search=juan');

    $response->assertForbidden();
});

test('search customers requires authentication', function () {
    $response = $this->getJson('/admin/pos-woo/customers?search=juan');

    $response->assertRedirect();
});
