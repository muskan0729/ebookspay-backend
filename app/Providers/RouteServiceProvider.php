<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // RateLimiter::for('api', function (Request $request) {
        //     return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        // });
        // RateLimiter::for('api', function (Request $request) {
        //     return Limit::perMinute(5000)->by($request->user()?->id ?: $request->ip());
        // });
        
RateLimiter::for('api', function (Request $request) {

    \Log::channel('throttle')->info('RATE LIMITER HIT', [
        'ip' => $request->ip()
    ]);


    $minuteKey = 'rpm_' . now()->format('H:i');

    // ðŸ‘‡ increment per minute count
    $rpm = cache()->increment($minuteKey);
    cache()->put($minuteKey, $rpm, 70); // 70 sec TTL (safe)

    // âœ… LOG RPM
    // \Log::channel('throttle')->info('RPM UPDATE', [
    //     'time' => now()->format('H:i'),
    //     'rpm' => $rpm,
    //     'ip' => $request->ip()
    // ]);
    
    // âœ… TPS KEY (per second)
    $secondKey = 'tps_' . now()->format('H:i:s');

    // ðŸ‘‡ increment per second count
    $tps = cache()->increment($secondKey);
    cache()->put($secondKey, $tps, 2); // 2 sec TTL

    // âœ… TPS LOG
    // \Log::channel('throttle')->info('TPS UPDATE', [
    //     'time' => now()->format('H:i:s'),
    //     'tps' => $tps,
    //     'ip' => $request->ip()
    // ]);
    
    
    $key = 'qr_api_' . $request->ip();
    $maxPerSecond = 10;

    $count = cache()->get($key, 0); // ðŸ‘ˆ count lo

    if ($count >= $maxPerSecond) {

        // \Log::channel('throttle')->info('THROTTLED: ' . $request->ip());
                // âœ… 2. THROTTLE LOG (sirf jab limit cross hogi)
        // \Log::channel('throttle')->info('THROTTLED', [
        //     'ip' => $request->ip(),
        //     'count' => $count
        // ]);

        return Limit::none()->response(function () {
            return response('Too Many Requests', 429);
        });
    }

    cache()->put($key, $count + 1, 1); // ðŸ‘ˆ increment with 1 sec TTL

    return Limit::none();
});

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
