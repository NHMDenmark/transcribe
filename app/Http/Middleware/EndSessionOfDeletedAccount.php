<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A deleted account cannot sign in (nobody knows its password), but a session that was already open
 * would go on working: this ends it on the next request.
 */
class EndSessionOfDeletedAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->anonymized_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('error', 'This account was deleted.');
        }

        return $next($request);
    }
}
