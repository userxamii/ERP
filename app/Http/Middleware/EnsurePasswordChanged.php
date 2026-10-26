<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->must_change_password
            && ! $request->routeIs('warehouse.auth.change-password', 'warehouse.auth.logout', 'logout')
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Change your temporary password before continuing.',
                    'mustChangePassword' => true,
                ], 423);
            }

            return redirect('/');
        }

        return $next($request);
    }
}
