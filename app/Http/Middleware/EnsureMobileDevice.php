<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileDevice
{
    /**
     * Pola User-Agent perangkat handphone (smartphone, bukan tablet).
     * Kita tidak mencocokkan "ipad" dan "tablet" agar shortcut hanya untuk phone.
     */
    protected const MOBILE_PATTERN = '/(android.+mobile|iphone|ipod|blackberry|opera mini|opera mobi|windows phone|iemobile|mobile safari)/i';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = (string) $request->header('User-Agent', '');

        if (! preg_match(self::MOBILE_PATTERN, $userAgent)) {
            if ($request->expectsJson()) {
                abort(403, 'Halaman ini hanya tersedia di perangkat handphone.');
            }

            return redirect()
                ->route('izin')
                ->with('warning', 'Shortcut izin hanya tersedia di perangkat handphone.');
        }

        return $next($request);
    }
}
