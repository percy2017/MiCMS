<?php

use Inertia\Testing\AssertableInertia as Assert;

test('only enabled modules are shared with the sidebar', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->get(route('admin.paquetes.index'))
        ->assertInertia(fn (Assert $page) => $page->has('enabledPackages'));
});

test('guests receive an empty enabledPackages array', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enabledPackages', []),
        );
});
