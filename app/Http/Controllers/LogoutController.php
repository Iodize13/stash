<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * App-side logout (Filament's logout route is only reachable by panel users).
 * Ending a sandbox deletes it and everything in it right away.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user?->isSandbox()) {
            $user->delete();
        }

        return redirect()->route('home');
    }
}
