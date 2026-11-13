<?php

namespace App\Services;

use Illuminate\Http\Request;

class LocalAccountSession
{
    public static function available(Request $request): bool
    {
        return app()->environment(['local', 'testing'])
            && blank(config('session.domain'))
            && in_array($request->ip(), ['127.0.0.1', '::1'], true)
            && (bool) preg_match('/\A(?:127\.0\.0\.1|localhost|phien-[a-f0-9]{32}\.localhost)\z/', $request->getHost());
    }

    public static function loginUrl(Request $request): string
    {
        // Each host has its own host-only session and remember-me cookies.
        // The random label identifies a workspace, never an authentication token.
        $host = 'phien-'.bin2hex(random_bytes(16)).'.localhost';
        $port = $request->getPort();
        $suffix = ($request->isSecure() && $port === 443) || (! $request->isSecure() && $port === 80) ? '' : ':'.$port;

        return $request->getScheme().'://'.$host.$suffix.$request->getBaseUrl().'/dang-nhap';
    }
}
