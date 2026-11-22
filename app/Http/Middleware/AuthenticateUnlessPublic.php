<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

/**
 * The login wall of the platform, with the door set from the Settings page: logged-in users
 * pass as usual; a visitor passes only on a route that config/access.php opens for the current
 * "anonymous access" setting, and is sent to the login page otherwise.
 */
class AuthenticateUnlessPublic extends Authenticate
{
    public function handle($request, Closure $next, ...$guards)
    {
        if (!$request->user() && $this->openToVisitors($request)) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    private function openToVisitors(Request $request): bool
    {
        $mode = Setting::current()->anonymousAccess();
        if ($mode === 'login_required' || !$request->route()) {
            return false;
        }

        $routes = config('access.public_routes');
        $open = $mode === 'submit' ? array_merge($routes['browse'], $routes['submit']) : $routes['browse'];
        $method = $request->isMethod('HEAD') ? 'GET' : $request->method();

        return in_array($method . ' ' . $request->route()->uri(), $open, true);
    }
}
