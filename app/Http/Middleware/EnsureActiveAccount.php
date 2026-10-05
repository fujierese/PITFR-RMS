<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            if ($token = $user->currentAccessToken()) {
                $token->delete();
            }

            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->is('api/*')) {
                return response()->json(['message' => 'This account is inactive.'], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'This account is inactive.']);
        }

        return $next($request);
    }
}
