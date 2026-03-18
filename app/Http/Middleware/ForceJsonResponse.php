<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force all API requests to return JSON responses.
 * 
 * This prevents Laravel from returning HTML redirects (e.g. for
 * email verification or authentication failures) on API routes.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Force the request to accept JSON so Laravel's exception
        // handler and middleware (EnsureEmailIsVerified, Authenticate,
        // etc.) always return JSON instead of HTML redirects.
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
