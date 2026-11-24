<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenants.{tenantId}.orders', function (User $user, int $tenantId): bool {
    return $user->hasRole('Super') || (int) $user->tenant_id === $tenantId;
});
