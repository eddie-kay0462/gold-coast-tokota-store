<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminRole::class,
            'staff_or_admin' => \App\Http\Middleware\EnsureStaffOrAdminRole::class,
        ]);

        // A guest hitting an API route gets 401, never a redirect. Returning
        // null here makes Authenticate throw AuthenticationException instead of
        // calling route('login') — a route this app has no reason to define,
        // since both frontends own their own login screens. Previously that
        // lookup threw RouteNotFoundException, so an expired admin session
        // surfaced as a 500 rather than a 401 on any request that didn't send
        // `Accept: application/json`.
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('api/*') ? null : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Anything under /api/* answers in JSON, including its failures.
        //
        // Without this, an unauthenticated request that doesn't send
        // `Accept: application/json` falls through to Laravel's web behaviour
        // and tries to redirect to a `login` route this app has no reason to
        // define — so a missing session surfaced as a 500 and an HTML error
        // page instead of a 401. The admin SPA always sends the header and so
        // never saw it; anything else hitting an API URL did.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
