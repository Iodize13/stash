<x-layouts.public title="Save link">
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="flex w-full max-w-md flex-col gap-5 border border-line bg-panel p-6 text-xs">
            <div class="flex items-center gap-3">
                <span class="flex size-6 items-center justify-center bg-accent text-[11px] font-bold text-canvas">{{ strtoupper(substr(config('app.name'), 0, 2)) }}</span>
                <span class="text-sm font-bold">Save to {{ config('app.name') }}</span>
            </div>

            @if ($saved)
                <div
                    class="flex flex-col items-center gap-3 py-4 text-center"
                    {{-- Close the popup on success; stays open when visited directly. --}}
                    x-data
                    x-init="if (window.opener) setTimeout(() => window.close(), 1500)"
                >
                    <x-material-icon name="check_circle" class="text-[36px] text-ok" />
                    <p class="font-sans text-base">Saved. Fetching it now.</p>
                    <p class="max-w-full truncate text-dim">{{ $saved->url }}</p>
                    <a href="{{ route('library') }}" target="_blank" class="text-accent hover:underline">Open library</a>
                </div>
            @elseif (! $canSave)
                <p class="flex items-center gap-2 bg-canvas p-3 text-muted">
                    <x-material-icon name="lock" class="text-[16px] text-hot" />
                    This account is read-only, so links cannot be saved.
                </p>
            @elseif ($url === '')
                <div class="flex flex-col gap-3 text-muted">
                    <p class="font-sans text-sm text-fg">Save any page in one click.</p>
                    <p>Drag this button to your bookmarks bar, then click it on any page you want to keep:</p>
                    <div>@include('partials.bookmarklet')</div>
                </div>
            @elseif ($existing)
                <div class="flex flex-col gap-3">
                    <p class="flex items-center gap-2 text-amber-400"><x-material-icon name="info" class="text-[16px]" /> Already in your library.</p>
                    <p class="font-sans text-sm">{{ $existing->title ?? $existing->url }}</p>
                    <a href="{{ route('articles.show', $existing) }}" target="_blank" class="w-fit bg-accent px-3 py-2 font-bold text-canvas">Open in reader</a>
                </div>
            @else
                <form method="POST" action="{{ route('save.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="url" value="{{ $url }}">
                    <input type="hidden" name="title" value="{{ $title }}">

                    <div class="flex flex-col gap-1">
                        @if ($title !== '')
                            <p class="font-sans text-base leading-snug">{{ $title }}</p>
                        @endif
                        <p class="truncate text-dim" title="{{ $url }}">{{ $url }}</p>
                    </div>

                    <label class="flex items-center gap-2 bg-canvas px-3">
                        <x-material-icon name="label" class="text-[16px] text-dim" />
                        <input
                            name="tags"
                            value="{{ old('tags') }}"
                            autofocus
                            placeholder="#tags (optional), then press Enter"
                            class="min-w-0 flex-1 border border-edge bg-transparent p-3 text-hot placeholder:text-dim focus:border-accent focus:outline-none"
                        >
                    </label>

                    @error('url')
                        <p class="text-hot" role="alert">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center justify-between">
                        <button type="button" onclick="window.close()" class="text-dim hover:text-fg">Cancel</button>
                        <button type="submit" class="flex items-center gap-2 bg-accent px-4 py-2.5 font-bold text-canvas hover:brightness-110">
                            <x-material-icon name="downloading" class="text-[16px]" /> Save link
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </main>
</x-layouts.public>
