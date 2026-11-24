<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenants.{tenantId}.orders', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === $tenantId;
});
