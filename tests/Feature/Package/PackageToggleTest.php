<?php

test('toggle flips the enabled flag using nWidart modules', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->patch(route('admin.paquetes.toggle', ['slug' => 'poswoo']))
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('admin.paquetes.toggle', ['slug' => 'poswoo']))
        ->assertRedirect();
});

test('guests cannot toggle', function () {
    $this->patch(route('admin.paquetes.toggle', ['slug' => 'poswoo']))
        ->assertRedirect(route('login'));
});
