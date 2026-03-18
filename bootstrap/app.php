<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

 $app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        
        // Force JSON responses on all API routes so Laravel never
        // returns HTML redirects (fixes login-loop for unverified emails).
        $middleware->prependToGroup('api', \App\Http\Middleware\ForceJsonResponse::class);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'install' => \App\Http\Middleware\CanInstall::class,
            'x_check' => \App\Http\Middleware\XCheck::class,
            'permission' => \App\Http\Middleware\Permission::class,
            'optional_auth' => \App\Http\Middleware\OptionaAuth::class,
            'force_json' => \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }
        });

        // Catch email-verification redirects and return JSON for API routes
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage() ?: 'Request failed.',
                ], $e->getStatusCode());
            }
        });
    })->create();
    
    // Register our Firebase provider so the Firebase bindings are available.
    $app->register(\App\Providers\FirebaseServiceProvider::class);

    return $app;
