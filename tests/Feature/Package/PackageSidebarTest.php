<?php

use App\Models\Package;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('only enabled and installed packages are shared with the sidebar', function () {
    $user = User::factory()->create();
    Package::factory()->enabled()->create(['name' => 'Enabled One']);
    Package::factory()->enabled()->create(['name' => 'Enabled Two']);
    Package::factory()->disabled()->create(['name' => 'Disabled One']);
    Package::factory()->enabled()->create(['installed' => false, 'name' => 'Not Installed']);

    $this->actingAs($user)
        ->get(route('admin.paquetes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('enabledPackages', 2)
            ->where('enabledPackages.0.label', 'Enabled One')
            ->where('enabledPackages.1.label', 'Enabled Two'),
        );
});

test('guests receive an empty enabledPackages array', function () {
    Package::factory()->enabled()->create(['name' => 'Should Not Show']);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enabledPackages', []),
        );
});

test('menu_label takes precedence over name in the shared prop', function () {
    $user = User::factory()->create();
    Package::factory()->enabled()->create([
        'name' => 'Customer Relationship Manager',
        'menu_label' => 'CRM',
    ]);

    $this->actingAs($user)
        ->get(route('admin.paquetes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enabledPackages.0.label', 'CRM'),
        );
});
