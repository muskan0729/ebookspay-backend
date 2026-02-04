<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RoleMiddleware;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register your role middleware
        Route::aliasMiddleware('role', RoleMiddleware::class);

        // Load api.php routes
        if (file_exists(base_path('routes/api.php'))) {
            Route::middleware('api')
                ->group(base_path('routes/api.php'));
        }
    }
}
