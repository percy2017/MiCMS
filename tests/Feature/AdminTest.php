<?php

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the admin panel', function () {
    $user = adminUser();
    $this->actingAs($user);

    $response = $this->get(route('admin'));
    $response->assertOk();
});
