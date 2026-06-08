<?php

use App\Models\Package;
use App\Models\User;

test('authenticated users can update a package', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->from(route('admin.paquetes.edit', ['package' => $package->id]))
        ->patch(route('admin.paquetes.update', ['package' => $package->id]), [
            'name' => 'New Name',
            'description' => 'Updated description',
            'config' => ['api_key' => 'new-key'],
        ])
        ->assertRedirect(route('admin.paquetes.index'))
        ->assertSessionHas('success');

    $fresh = $package->fresh();
    expect($fresh->name)->toBe('New Name');
    expect($fresh->description)->toBe('Updated description');
    expect($fresh->config)->toBe(['api_key' => 'new-key']);
});

test('name is required', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.paquetes.update', ['package' => $package->id]), [
            'name' => '',
        ])
        ->assertSessionHasErrors('name');
});

test('name must be unique excluding the current package', function () {
    $user = User::factory()->create();
    Package::factory()->create(['name' => 'Existing']);
    $package = Package::factory()->create(['name' => 'Mine']);

    $this->actingAs($user)
        ->patch(route('admin.paquetes.update', ['package' => $package->id]), [
            'name' => 'Existing',
        ])
        ->assertSessionHasErrors('name');
});

test('config must be an array', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.paquetes.update', ['package' => $package->id]), [
            'name' => 'Valid',
            'config' => 'not-an-array',
        ])
        ->assertSessionHasErrors('config');
});
