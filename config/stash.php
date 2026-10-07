<?php

return [
    // "Try the demo" creates a private, temporary sandbox account per visitor.
    'demo_login' => (bool) env('DEMO_LOGIN', true),

    'sandbox' => [
        'lifetime_hours' => (int) env('SANDBOX_LIFETIME_HOURS', 24),
        // Total articles a sandbox may hold (including the copied samples).
        'max_links' => (int) env('SANDBOX_MAX_LINKS', 15),
        // Refuse new sandboxes past this many live ones.
        'max_active' => (int) env('SANDBOX_MAX_ACTIVE', 200),
        // Ready articles (and their highlights) of this user are copied into each sandbox.
        'template_email' => env('SANDBOX_TEMPLATE_EMAIL'),
        'template_articles' => 5,
    ],

    // Shown on the landing page when set, e.g. https://github.com/you/stash
    'repository_url' => env('REPOSITORY_URL'),
];
