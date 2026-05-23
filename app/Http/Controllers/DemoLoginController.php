<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs visitors in as the shared demo user. Safe to expose because the demo
 * role is read-only everywhere (enforced by policies, covered by tests).
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('stash.demo_login'), 404);

        // whereHas rather than User::role(): the latter throws if the role was never created.
        $demo = User::whereHas('roles', fn ($query) => $query->where('name', 'demo'))->oldest('id')->first();

        abort_if($demo === null, 404);

        Auth::login($demo);
        $request->session()->regenerate();

        return redirect()->route('library');
    }
}
