<?php

namespace App\Http\Middleware;

<<<<<<< HEAD
=======
use App\Models\User;
>>>>>>> 28aff9e (Integrate connected ERP workflows and sample data)
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

<<<<<<< HEAD
        if ($user !== null && ! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

=======
        if ($user instanceof User && ! $user->is_active) {
>>>>>>> 28aff9e (Integrate connected ERP workflows and sample data)
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This account is inactive.',
                ], 403);
            }

<<<<<<< HEAD
            return redirect('/');
=======
            abort(403, 'This account is inactive.');
>>>>>>> 28aff9e (Integrate connected ERP workflows and sample data)
        }

        return $next($request);
    }
}
