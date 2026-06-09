<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to the Scramble API docs UI to admins only,
 * regardless of environment (in local mode the package middleware
 * bypasses the gate, which is not what we want for this CMS).
 */
class EnsureUserCanViewApiDocs
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! Gate::forUser($user)->allows('viewApiDocs')) {
            abort(403);
        }

        return $next($request);
    }
}
