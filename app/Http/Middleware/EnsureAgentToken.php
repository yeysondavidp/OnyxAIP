<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the MCP endpoint (ADR-004): only an `agent` account gets in, never a PM.
 * auth:sanctum runs first and, with config('sanctum.guard') empty, only ever
 * authenticates a real bearer token — never a browser session.
 */
class EnsureAgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAgent()) {
            abort(403, 'This endpoint only accepts agent access keys.');
        }

        return $next($request);
    }
}
