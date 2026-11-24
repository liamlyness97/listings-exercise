<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Take-home auth stub.
 *
 * A real deployment would authenticate the request (Sanctum, session, etc.).
 * To keep this exercise focused on the feature rather than on auth plumbing,
 * every API request is resolved as the seeded demo user. Build user-scoped
 * work against $request->user() / auth()->user() exactly as you normally would.
 */
class ActAsDemoUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // Resolved once per request and handed to the auth guard, so
        // $request->user(), auth()->user(), the `auth` middleware and policies
        // all see the same user. (Replacing only the request's user resolver
        // left auth()->user() null and every policy treating requests as guests.)
        //
        // This is null until the database is seeded (`php artisan migrate --seed`),
        // which is the same shape as a genuinely unauthenticated request.
        $demoUser = User::query()->orderBy('id')->first();

        if ($demoUser !== null) {
            Auth::setUser($demoUser);
        } else {
            // Clear any user left on the guard by an earlier request on the
            // same app instance, so "no user" really means a guest.
            Auth::forgetUser();
        }

        return $next($request);
    }
}
