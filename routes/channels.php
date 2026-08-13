<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('facility-requests.admin', function ($user) {
    // Only allow facility administrators and admin roles to listen on the admin channel
    return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
});

Broadcast::channel('facility-requests.custodian.{id}', function ($user, $id) {
    // Allow only the custodian themselves and ensure they have a custodian role
    if (! $user) return false;
    if ((int) $user->id !== (int) $id) return false;
    return method_exists($user, 'isCustodian') ? $user->isCustodian() : false;
});
