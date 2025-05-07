<?php

namespace Reyemtech\Sail\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        // Force HTTPS except for Artisan Serve & CI E2E Tests
        if (request()->getPort() !== 8000) {
            URL::forceScheme('https');
        }

        // Redirect to HTTPS if accessed via HTTP
        if (!$request->secure()) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
