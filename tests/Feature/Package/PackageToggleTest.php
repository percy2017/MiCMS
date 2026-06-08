<?php

use App\Models\Package;
use App\Models\User;

test('toggle flips the enabled flag', function () {
    $user = User::factory()->create();
    $package = Package::factory()->disabled()->create();

    $this->actingAs($user)
        ->patch(route('admin.paquetes.toggle', ['package' => $package->id]))
        ->assertRedirect();

    expect($package->fresh()->enabled)->toBeTrue();

    $this->actingAs($user)
        ->patch(route('admin.paquetes.toggle', ['package' => $package->id]))
        ->assertRedirect();

    expect($package->fresh()->enabled)->toBeFalse();
});

test('toggle works the other way for an enabled package', function () {
    $user = User::factory()->create();
    $package = Package::factory()->enabled()->create();

    $this->actingAs($user)
        ->patch(route('admin.paquetes.toggle', ['package' => $package->id]))
        ->assertRedirect();

    expect($package->fresh()->enabled)->toBeFalse();
});

test('guests cannot toggle', function () {
    $package = Package::factory()->create();

    $this->patch(route('admin.paquetes.toggle', ['package' => $package->id]))
        ->assertRedirect(route('login'));
});
