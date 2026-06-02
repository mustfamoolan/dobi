<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('system.users', function ($user) {
    return ['id' => $user->id, 'name' => $user->name, 'last_seen' => $user->last_seen];
});
