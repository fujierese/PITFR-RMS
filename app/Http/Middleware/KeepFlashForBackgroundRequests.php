<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page messages ("Request submitted", warnings, errors) are kept for the next request
 * only. The request form checks availability in the background, and a check that
 * reached the server between a save and the page it redirects to used up the message,
 * so it never appeared. Background (AJAX/JSON) requests now leave messages for the next
 * full page.
 */
class KeepFlashForBackgroundRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession() && ($request->ajax() || $request->expectsJson()) && $request->isMethodSafe()) {
            $request->session()->reflash();
        }

        return $response;
    }
}
