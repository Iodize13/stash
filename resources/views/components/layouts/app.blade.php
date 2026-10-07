<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Library' }} · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        x-data
        @keydown.window.slash="if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus() }"
        @keydown.window.meta.k.prevent="$refs.search.focus()"
        @keydown.window.ctrl.k.prevent="$refs.search.focus()"
    >
        <div class="flex min-h-screen">
            <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col justify-between border-r border-line bg-rail p-4 lg:flex">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-3 px-2 py-1">
                        <div class="flex size-7 items-center justify-center bg-accent text-sm font-bold text-canvas">
                            {{ strtoupper(substr(config('app.name'), 0, 2)) }}
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span class="text-sm font-bold">{{ config('app.name') }}</span>
                            <span class="text-[10px] text-accent">READ-LATER // ARCHIVE</span>
                        </div>
                    </div>

                    <nav class="flex flex-col gap-1 text-xs">
                        <a
                            href="{{ route('library') }}"
                            @class([
                                'flex items-center gap-3 border-l-2 px-3 py-2',
                                'border-accent bg-raised font-medium text-accent' => request()->routeIs('library', 'articles.show'),
                                'border-transparent text-muted hover:text-fg' => ! request()->routeIs('library', 'articles.show'),
                            ])
                        >
                            <x-material-icon name="auto_stories" class="text-[18px]" />
                            Library &amp; Queue
                        </a>
                        <a
                            href="{{ route('highlights') }}"
                            wire:navigate
                            @class([
                                'flex items-center gap-3 border-l-2 px-3 py-2',
                                'border-accent bg-raised font-medium text-accent' => request()->routeIs('highlights'),
                                'border-transparent text-muted hover:text-fg' => ! request()->routeIs('highlights'),
                            ])
                        >
                            <x-material-icon name="border_color" class="text-[18px]" />
                            Highlights &amp; Notes
                        </a>
                        {{-- Sandbox guests get no admin panel, collections or tokens: they can never publish. --}}
                        @if (auth()->user()?->hasAnyRole(['admin', 'demo']))
                        <a href="{{ App\Filament\Resources\Collections\CollectionResource::getUrl() }}" class="flex items-center gap-3 border-l-2 border-transparent px-3 py-2 text-muted hover:text-fg">
                            <x-material-icon name="folder_shared" class="text-[18px]" />
                            Collections
                        </a>
                        <a
                            href="{{ route('settings.tokens') }}"
                            wire:navigate
                            @class([
                                'flex items-center gap-3 border-l-2 px-3 py-2',
                                'border-accent bg-raised font-medium text-accent' => request()->routeIs('settings.tokens'),
                                'border-transparent text-muted hover:text-fg' => ! request()->routeIs('settings.tokens'),
                            ])
                        >
                            <x-material-icon name="key" class="text-[18px]" />
                            API tokens
                        </a>
                        <a href="{{ url('/admin') }}" class="flex items-center gap-3 border-l-2 border-transparent px-3 py-2 text-muted hover:text-fg">
                            <x-material-icon name="admin_panel_settings" class="text-[18px]" />
                            Admin panel
                        </a>
                        @endif
                    </nav>
                </div>

                <div class="flex flex-col gap-3 border-t border-line pt-4">
                    @auth
                        <div class="flex items-center justify-between bg-panel p-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="flex size-7 items-center justify-center bg-raised font-sans text-xs text-accent">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                </div>
                                <div class="flex flex-col leading-tight">
                                    <span class="font-sans text-xs font-medium">{{ auth()->user()->name }}</span>
                                    <span class="text-[10px] text-dim">{{ auth()->user()->getRoleNames()->first() }}</span>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" @if (auth()->user()->isSandbox()) onsubmit="return confirm('End the sandbox? Everything you saved in it is deleted.')" @endif>
                                @csrf
                                <button type="submit" class="p-1 text-muted hover:text-fg" title="Log out" aria-label="Log out">
                                    <x-material-icon name="logout" class="text-[16px]" />
                                </button>
                            </form>
                        </div>
                    @endauth
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex h-14 items-center justify-between gap-4 border-b border-line bg-[#1c2025] px-4 lg:px-8">
                    <form method="GET" action="{{ route('library') }}" class="flex min-w-0 flex-1 items-center gap-2 bg-canvas px-3 py-1.5 lg:max-w-2xl">
                        <x-material-icon name="search" class="text-[17px] text-dim" />
                        <input
                            x-ref="search"
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search archive across titles, tags, and URLs… (press / anywhere)"
                            class="min-w-0 flex-1 border border-edge bg-transparent px-3 py-2 text-xs text-fg placeholder:text-dim focus:border-accent focus:outline-none"
                        >
                        <kbd class="hidden bg-panel px-1.5 py-0.5 text-[10px] text-dim sm:block">⌘K</kbd>
                    </form>

                    <div class="flex shrink-0 items-center gap-2 text-xs">
                        <livewire:queue-counter />
                    </div>
                </header>

                <main class="flex flex-col gap-6 p-4 lg:p-8">
                    @if (auth()->user()?->isSandbox())
                        <div class="flex flex-wrap items-center justify-between gap-2 border border-accent/40 bg-panel px-4 py-2.5 text-xs">
                            <p class="flex items-center gap-2 text-muted">
                                <x-material-icon name="science" class="text-[16px] text-accent" />
                                <span><span class="text-accent">Private demo sandbox.</span> Save links, read and highlight. Only you can see it; it is deleted {{ auth()->user()->sandbox_expires_at->diffForHumans() }}.</span>
                            </p>
                            <span class="text-dim">{{ auth()->user()->articles()->count() }} / {{ config('stash.sandbox.max_links') }} links</span>
                        </div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
