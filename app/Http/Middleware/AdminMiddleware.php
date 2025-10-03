<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->is_admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }
            return redirect()->route('home')->with('error', 'Unauthorized. Admin access required.');
        }

        // Check for failed login attempts if admin
        if (Auth::user()->is_admin && $this->isAccountLocked(Auth::user())) {
            Auth::logout();
            return response()->json(['message' => 'Account locked due to multiple failed login attempts. Please contact support.'], 423);
        }

        return $next($request);
    }

    private function isAccountLocked($user)
    {
        $key = 'admin_login_attempts_' . $user->id;
        $attempts = cache()->get($key, 0);
        return $attempts >= 5; // Lock after 5 failed attempts
    }
} 