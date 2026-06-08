<?php

use App\Models\Package;
use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;

test('public page passes enabledPackages prop', function () {
    Package::factory()->enabled()->create(['slug' => 'chat-widget', 'name' => 'Chat']);
    Package::factory()->disabled()->create(['slug' => 'crm', 'name' => 'CRM']);

    $home = Page::factory()->create([
        'slug' => 'home',
        'status' => Page::STATUS_PUBLISHED,
        'is_home' => true,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('enabledPackages', 1)
            ->where('enabledPackages.0.slug', 'chat-widget'),
        );
});

test('public page excludes non-installed packages', function () {
    Package::factory()->enabled()->create(['slug' => 'chat-widget', 'name' => 'Chat', 'installed' => false]);
    $home = Page::factory()->create([
        'slug' => 'home',
        'status' => Page::STATUS_PUBLISHED,
        'is_home' => true,
    ]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('enabledPackages', []),
        );
});
