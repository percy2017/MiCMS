<?php

use App\Models\Package;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('admin.paquetes.index'))->assertRedirect(route('login'));
});

test('authenticated users can see the packages list', function () {
    $user = User::factory()->create();
    $packages = Package::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.paquetes.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/paquetes/index')
            ->has('packages', 3)
            ->has('categories'),
        );
});

test('packages list passes categories map to the view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.paquetes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.communication', 'Comunicación')
            ->where('categories.business', 'Negocios')
            ->where('categories.general', 'General'),
        );
});
