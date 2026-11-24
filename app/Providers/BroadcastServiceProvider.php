<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTenantForAdmin;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes([
            'middleware' => ['web', 'auth', EnsureTenantForAdmin::class],
        ]);

        require base_path('routes/channels.php');
    }
}
