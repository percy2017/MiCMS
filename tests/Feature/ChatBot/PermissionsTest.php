<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('admin has all chatbot permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->can('view chatbot'))->toBeTrue();
    expect($user->can('view chats'))->toBeTrue();
    expect($user->can('reply chatbot'))->toBeTrue();
    expect($user->can('view chatbot conversations'))->toBeTrue();
    expect($user->can('delete chatbot conversations'))->toBeTrue();
    expect($user->can('update chatbot widget'))->toBeTrue();
});

test('editor has read + reply permissions but not delete or widget edit', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    expect($user->can('view chatbot'))->toBeTrue();
    expect($user->can('view chats'))->toBeTrue();
    expect($user->can('view chatbot conversations'))->toBeTrue();
    expect($user->can('reply chatbot'))->toBeTrue();

    expect($user->can('delete chatbot conversations'))->toBeFalse();
    expect($user->can('update chatbot widget'))->toBeFalse();
});

test('user role has no chatbot permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    expect($user->can('view chatbot'))->toBeFalse();
    expect($user->can('view chats'))->toBeFalse();
    expect($user->can('reply chatbot'))->toBeFalse();
});

test('all chatbot permissions are seeded', function () {
    $expected = [
        'view chatbot',
        'view chats',
        'reply chatbot',
        'view chatbot conversations',
        'delete chatbot conversations',
        'update chatbot widget',
    ];

    foreach ($expected as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue("Permission '{$name}' should exist");
    }
});
