<?php

return [
    // One-click "Try the demo" sign-in as the read-only demo user.
    'demo_login' => (bool) env('DEMO_LOGIN', true),

    // Shown on the landing page when set, e.g. https://github.com/you/stash
    'repository_url' => env('REPOSITORY_URL'),
];
