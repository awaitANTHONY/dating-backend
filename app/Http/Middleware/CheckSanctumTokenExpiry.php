<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckSanctumTokenExpiry
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force JSON response for API routes
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        // Only process if we have a bearer token
        $bearerToken = $request->bearerToken();
        
        if (!$bearerToken) {
            // If no token on API route, return JSON error
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Authentication token required'
                ], 401);
            }
            return $next($request);
        }

        // Find the token in the database
        $accessToken = PersonalAccessToken::findToken($bearerToken);
        
        if (!$accessToken) {
            return response()->json([
                'status' => false, 
                'message' => 'Invalid authentication token'
            ], 401);
        }

        // Check if token has expired (if expires_at is set)
        if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
            return response()->json([
                'status' => false, 
                'message' => 'Authentication token has expired'
            ], 401);
        }

        // Update last used timestamp
        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
