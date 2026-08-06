<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        $user = Auth::user();
        $userRole = $user->role;

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if ($role === 'custodian' && str_starts_with($userRole, 'custodian')) {
                return $next($request);
            }

            if ($role === 'admin' && $user->isAdmin()) {
                return $next($request);
            }

            if ($role === 'requestor' && $user->isRequestee()) {
                return $next($request);
            }

            if ($userRole === $role) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized');
    }
}