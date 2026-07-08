<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isPaused()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sua conta está pausada. Entre em contato com o administrador.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'login' => 'Sua conta está pausada. Entre em contato com o administrador.',
            ]);
        }

        return $next($request);
    }
}
