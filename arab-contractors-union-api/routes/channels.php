<?php

use Illuminate\Support\Facades\Broadcast;

// Admin/staff real-time notifications (contractor activation, event RSVP, payments...).
// Explicit instanceof check so a Contractor token can never authorize this channel just
// because its numeric id happens to match a User id — Sanctum resolves either model.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user instanceof \App\Models\User && (int) $user->id === (int) $id;
});
