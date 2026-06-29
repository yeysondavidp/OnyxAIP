<?php

use App\Http\Middleware\EnsurePmRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers on every web response (§14.3).
        $middleware->web(append: [SecurityHeaders::class]);

        // role:pm → EnsurePmRole (used on PM portal route group)
        $middleware->alias(['role' => EnsurePmRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Render a plain-language, mobile-first page for invalid/expired signed URLs
        // rather than a generic 403. Expired links get a distinct message explaining
        // that 72-hour links need to be resent (§14.3, US-01.4).
        // Detect expiry by checking whether the `expires` query param (set by
        // temporarySignedRoute) is in the past — same exception class for both cases.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            $expires   = $request->query('expires');
            $isExpired = $expires && (int) $expires < time();
            $view      = $isExpired ? 'technician.link-expired' : 'technician.link-invalid';

            return response()->view($view, [], 403);
        });
    })->create();
