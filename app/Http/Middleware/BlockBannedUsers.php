<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockBannedUsers
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ($user->status == 4 || $user->is_banned)) {
            // Revoke all tokens so they can't keep using existing sessions
            $user->tokens()->delete();

            return response()->json([
                'status' => false,
                'message' => 'Your account has been banned. Please contact support for assistance.',
                'code' => 'ACCOUNT_BANNED',
            ], 403);
        }

        return $next($request);
    }
}
