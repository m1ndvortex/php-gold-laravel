<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for authenticated users within their tenant
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return $user && tenant_context() && tenant_context()->id == $tenantId;
});

// Private channel for user-specific notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user && $user->id == $userId;
});

// Private channel for dashboard updates within a tenant
Broadcast::channel('dashboard.{tenantId}', function ($user, $tenantId) {
    return $user && tenant_context() && tenant_context()->id == $tenantId;
});

// Private channel for inventory updates within a tenant
Broadcast::channel('inventory.{tenantId}', function ($user, $tenantId) {
    return $user && tenant_context() && tenant_context()->id == $tenantId;
});

// Private channel for notifications within a tenant
Broadcast::channel('notifications.{tenantId}', function ($user, $tenantId) {
    return $user && tenant_context() && tenant_context()->id == $tenantId;
});