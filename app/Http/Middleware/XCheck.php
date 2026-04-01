<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;

class XCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $x_api_key = $request->header('X-API-KEY');

        // Handle duplicate headers: if comma-separated (empty + real key), extract non-empty value
        if (str_contains($x_api_key ?? '', ',')) {
            $parts = array_map('trim', explode(',', $x_api_key));
            $x_api_key = collect($parts)->first(fn($v) => $v !== '') ?: '';
        }

        if ($x_api_key == '') {
            return response()->json(['status' => false, 'message' => 'Your API key is missing.']);
        } else {

            if (!in_array($x_api_key, [config('app.x_api_key'), config('app.x_api_key2')])) {
                return response()->json(['status' => false, 'message' => 'Your API key is invalid or incorrect.']);
            }

            return $next($request);
        }
    }
}
