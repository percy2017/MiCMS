<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chatbot.admin', function ($user) {
    return $user && $user->can('view chats');
});

Broadcast::channel('chatbot.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
