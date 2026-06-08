<?php

use App\Models\Package;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot edit packages', function () {
    $package = Package::factory()->create();

    $this->get(route('admin.paquetes.edit', ['package' => $package->id]))
        ->assertRedirect(route('login'));
});

test('authenticated users can see the package edit form', function () {
    $user = User::factory()->create();
    $package = Package::factory()->withConfig(['api_key' => 'abc'])->create();

    $this->actingAs($user)
        ->get(route('admin.paquetes.edit', ['package' => $package->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/paquetes/edit')
            ->where('package.id', $package->id)
            ->where('package.name', $package->name)
            ->where('package.config.api_key', 'abc'),
        );
});
