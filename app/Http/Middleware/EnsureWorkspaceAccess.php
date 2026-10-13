<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account has been deactivated.']);
        }
        if ($user) {
            $name = $request->route()?->getName() ?? '';
            if ($name === 'logout') {
                return $next($request);
            }
            abort_unless($user->hasWorkspaceAccess(), 403);
            $supportRoute = str_starts_with($name, 'conversations.') || str_starts_with($name, 'profile.') || str_starts_with($name, 'password.') || str_starts_with($name, 'verification.') || in_array($name, ['home', 'logout', 'locale.update'], true);
            abort_unless($supportRoute || $user->isAdmin(), 403);
        }

        return $next($request);
    }
}
