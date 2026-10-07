<?php

namespace App\Http\Controllers;

use App\Actions\CreateSandbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Try the demo": signs the visitor in to a fresh private sandbox.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request, CreateSandbox $createSandbox): RedirectResponse
    {
        abort_unless(config('stash.demo_login'), 404);

        $sandbox = $createSandbox->handle();

        abort_if($sandbox === null, 503, 'The demo is busy right now. Please try again later.');

        Auth::login($sandbox);
        $request->session()->regenerate();

        return redirect()->route('library');
    }
}
