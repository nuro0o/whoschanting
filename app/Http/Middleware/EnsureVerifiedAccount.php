<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        // Guest seats stay available, but signing in requires a verified address.
        if ($request->user() !== null) {
            return app(EnsureEmailIsVerified::class)->handle($request, $next);
        }

        return $next($request);
    }
}
