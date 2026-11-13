<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/bang-dieu-khien';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('password-recovery', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip())->response(function (Request $request, array $headers) {
                $retryAfter = max(1, (int) $headers['Retry-After']);
                $message = "Bạn đã gửi nhiều yêu cầu khôi phục mật khẩu. Vui lòng chờ {$retryAfter} giây rồi thử lại.";
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'retry_after' => $retryAfter], 429, $headers)
                        ->header('Cache-Control', 'no-store, private');
                }
                $email = $request->input('email');
                $email = is_string($email) ? mb_substr($email, 0, 255) : '';

                return response()->view('auth.forgot', compact('email', 'retryAfter', 'message'), 429, $headers)
                    ->header('Cache-Control', 'no-store, private');
            });
        });

        $this->routes(function () {
            Route::middleware('web')->group(base_path('routes/web.php'));
            Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));
        });
    }
}
