@php
    $steps = [
        ['add_link', 'text-accent', 'Save', 'Paste a link, click the bookmarklet, or use the API. It is normalized and de-duplicated, then queued.', 'Livewire · Sanctum API'],
        ['sync', 'text-accent', 'Fetch & extract', 'A queue worker downloads the page behind an SSRF guard, extracts the article and sanitizes it.', 'Redis queue · Readability · HtmlSanitizer'],
        ['border_color', 'text-hot', 'Read & highlight', 'A distraction-free reader. Select any passage to highlight it, add a note and tags.', 'Alpine · text-quote selectors'],
        ['folder_shared', 'text-ok', 'Publish', 'Group articles into a collection and share its highlights, with an Atom feed and Markdown export.', 'Filament · Policies'],
    ];
    $stack = ['Laravel 13', 'Livewire', 'Alpine.js', 'Tailwind CSS', 'FilamentPHP', 'PostgreSQL', 'Redis queues', 'Sanctum', 'Spatie Permission', 'Spatie Activity Log', 'Pest', 'Docker / Sail', 'GitHub Actions'];
@endphp

<x-layouts.public
    title="Read-later & highlights"
    description="Stash saves links, fetches and cleans the article in a background queue, lets you highlight what matters, and publishes curated collections."
>
    <header class="border-b border-line bg-rail">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 text-xs lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="flex size-6 items-center justify-center bg-accent text-[11px] font-bold text-canvas">{{ strtoupper(substr(config('app.name'), 0, 2)) }}</span>
                <span class="text-sm font-bold">{{ config('app.name') }}</span>
            </a>
            <nav class="flex items-center gap-1 text-muted">
                @if (config('stash.repository_url'))
                    <a href="{{ config('stash.repository_url') }}" class="px-2.5 py-1.5 hover:text-fg">Source</a>
                @endif
                @auth
                    <a href="{{ route('library') }}" class="bg-accent px-3 py-1.5 font-bold text-canvas hover:brightness-110">Open library</a>
                @else
                    <a href="{{ route('library') }}" class="px-2.5 py-1.5 hover:text-fg">Sign in</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto flex max-w-6xl flex-col gap-16 px-4 py-16 lg:px-8 lg:py-24">
        <section class="flex max-w-3xl flex-col gap-6">
            <p class="text-xs text-accent">// READ-LATER · HIGHLIGHTS · COLLECTIONS</p>
            <h1 class="font-sans text-4xl font-semibold leading-tight lg:text-6xl">Save links. Read them clean. Keep the parts worth remembering.</h1>
            <p class="max-w-2xl font-sans text-lg leading-relaxed text-muted">
                {{ config('app.name') }} fetches every saved article in a background queue, strips it down to the text, lets you highlight passages with notes, and publishes the highlights you choose as a shareable collection.
            </p>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                @guest
                    @if ($demoAvailable)
                        <form method="POST" action="{{ route('demo') }}">
                            @csrf
                            <button type="submit" class="flex items-center gap-2 bg-accent px-5 py-3 font-bold text-canvas hover:brightness-110">
                                <x-material-icon name="play_arrow" class="text-[18px]" /> Try the demo
                            </button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('library') }}" class="flex items-center gap-2 bg-accent px-5 py-3 font-bold text-canvas hover:brightness-110">
                        <x-material-icon name="auto_stories" class="text-[18px]" /> Open your library
                    </a>
                @endguest
                @if ($sample)
                    <a href="{{ route('collections.show', $sample->slug) }}" class="flex items-center gap-2 border border-line px-5 py-3 text-muted hover:text-fg">
                        See a public collection <x-material-icon name="north_east" class="text-[16px]" />
                    </a>
                @endif
            </div>
            @guest
                @if ($demoAvailable)
                    <p class="text-xs text-dim">The demo signs you in as a read-only user: browse the library, reader and admin panel; nothing can be changed.</p>
                @endif
            @endguest
        </section>

        <section class="flex flex-col gap-6" aria-labelledby="how">
            <h2 id="how" class="text-xs tracking-wider text-dim">// HOW IT WORKS</h2>
            <ol class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as [$icon, $color, $title, $body, $tech])
                    <li class="relative flex flex-col gap-3 bg-panel p-5">
                        <div class="flex items-center justify-between">
                            <x-material-icon :name="$icon" @class(['text-[22px]', $color]) />
                            <span class="text-xs text-dim">0{{ $loop->iteration }}</span>
                        </div>
                        <h3 class="font-sans text-lg font-semibold">{{ $title }}</h3>
                        <p class="font-sans text-sm leading-relaxed text-muted">{{ $body }}</p>
                        <p class="mt-auto border-t border-line pt-3 text-[11px] text-dim">{{ $tech }}</p>
                        @unless ($loop->last)
                            <x-material-icon name="arrow_forward" class="absolute -right-3 top-1/2 z-10 hidden -translate-y-1/2 bg-canvas text-[18px] text-line lg:block" />
                        @endunless
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="grid gap-3 lg:grid-cols-3" aria-label="Engineering notes">
            <div class="flex flex-col gap-2 bg-panel p-5">
                <p class="flex items-center gap-2 text-xs text-hot"><x-material-icon name="shield" class="text-[16px]" /> SSRF-SAFE FETCHING</p>
                <p class="font-sans text-sm leading-relaxed text-muted">Only public IPs on ports 80/443, re-checked on every redirect, with the connection pinned to the vetted address to defeat DNS rebinding.</p>
            </div>
            <div class="flex flex-col gap-2 bg-panel p-5">
                <p class="flex items-center gap-2 text-xs text-ok"><x-material-icon name="replay" class="text-[16px]" /> RESILIENT QUEUE</p>
                <p class="font-sans text-sm leading-relaxed text-muted">Rate limits and server errors retry with backoff; permanent failures are reported with a reason and can be retried from the UI or admin panel.</p>
            </div>
            <div class="flex flex-col gap-2 bg-panel p-5">
                <p class="flex items-center gap-2 text-xs text-accent"><x-material-icon name="lock" class="text-[16px]" /> POLICY-ENFORCED ROLES</p>
                <p class="font-sans text-sm leading-relaxed text-muted">The demo role is read-only through policies, not hidden buttons, so direct requests are refused too, and tests cover each write path.</p>
            </div>
        </section>

        <section class="flex flex-col gap-4" aria-labelledby="stack">
            <h2 id="stack" class="text-xs tracking-wider text-dim">// BUILT WITH</h2>
            <ul class="flex flex-wrap gap-2 text-xs">
                @foreach ($stack as $item)
                    <li class="bg-panel px-3 py-1.5 text-muted">{{ $item }}</li>
                @endforeach
            </ul>
        </section>
    </main>

    <footer class="border-t border-line bg-rail">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-6 text-xs text-dim lg:px-8">
            <span class="flex items-center gap-2"><span class="size-3 bg-accent"></span> {{ config('app.name') }}</span>
            @if (config('stash.repository_url'))
                <a href="{{ config('stash.repository_url') }}" class="hover:text-fg">Source code</a>
            @endif
        </div>
    </footer>
</x-layouts.public>
