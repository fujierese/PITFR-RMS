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

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if ($role === 'custodian' && $user->isCustodian()) {
                return $next($request);
            }

            if ($role === 'admin' && $user->hasRole('admin')) {
                return $next($request);
            }

            if ($role === 'system_admin' && $user->isSystemAdmin()) {
                return $next($request);
            }

            if ($role === 'supply_office' && $user->isSupplyOffice()) {
                return $next($request);
            }

            if ($role === 'requestor' && $user->isRequestee()) {
                return $next($request);
            }

            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized');
    }
}