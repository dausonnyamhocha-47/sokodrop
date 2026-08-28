<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRole middleware.
 *
 * Restricts a route/route-group to one or more of the four Sokodrop roles.
 * Registered as an alias (e.g. 'role') in bootstrap/app.php and used in
 * routes/api.php like: ->middleware('role:merchant,admin')
 *
 * Runs AFTER Sanctum's auth:sanctum middleware, so $request->user() is
 * guaranteed to be populated when this executes.
 */
class CheckRole
{
    /**
     * @param  string  ...$roles  One or more allowed roles for this route.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Huna ruhusa ya kufikia rasilimali hii. (You do not have permission to access this resource.)',
            ], 403);
        }

        return $next($request);
    }
}