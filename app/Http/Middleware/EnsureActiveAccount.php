<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && (! $request->user()->is_active || $request->user()->status !== 'ACTIVE')) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Tài khoản đã bị khóa hoặc vô hiệu hóa.');
        }
        $response = $next($request);
        $response->headers->set('X-Request-ID', $request->attributes->get('request_id'));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
