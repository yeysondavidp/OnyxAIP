<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards PM routes — only UserRole::Pm may enter.
 *
 * client_user and technician are denied (403), not redirected to login,
 * so a logged-in non-PM who lands here gets a clear denial rather than
 * a confusing redirect loop.
 */
class EnsurePmRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::Pm) {
            abort(403, 'This area is restricted to Project Managers.');
        }

        return $next($request);
    }
}
