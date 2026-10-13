<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->user()->role === 'agent') {
            $intended = $request->session()->get('url.intended');
            $path = $intended ? parse_url($intended, PHP_URL_PATH) : null;
            $allowedPaths = [parse_url(route('conversations.index'), PHP_URL_PATH), parse_url(route('profile.edit'), PHP_URL_PATH)];
            $allowed = $path && collect($allowedPaths)->contains(fn (string $allowedPath): bool => $path === $allowedPath || str_starts_with($path, $allowedPath.'/'));
            if ($intended && ! $allowed) {
                $request->session()->forget('url.intended');
            }
        }

        return redirect()->intended(route('conversations.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
