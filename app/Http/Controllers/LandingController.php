<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('landing', [
            'sample' => Collection::public()->latest('updated_at')->first(['title', 'slug']),
            'demoAvailable' => config('stash.demo_login'),
        ]);
    }
}
