@php
    $statusStyles = [
        'ready' => ['icon' => 'verified', 'color' => 'text-ok'],
        'queued' => ['icon' => 'schedule', 'color' => 'text-accent'],
        'fetching' => ['icon' => 'sync', 'color' => 'text-accent'],
        'failed' => ['icon' => 'warning', 'color' => 'text-hot'],
    ];
@endphp

<div class="flex flex-col gap-6" wire:poll.3s>
    @can('create', App\Models\Article::class)
        <section class="flex flex-col gap-4 bg-panel p-6">
            <div class="flex items-center gap-2.5 border-b border-line pb-2">
                <x-material-icon name="bolt" class="text-[20px] text-accent" />
                <h2 class="text-sm font-bold">Save a link</h2>
            </div>

            <form wire:submit="ingest" class="flex flex-col gap-3 lg:flex-row">
                <label class="flex flex-1 items-center gap-2 bg-canvas px-3">
                    <x-material-icon name="add_link" class="text-[18px] text-dim" />
                    <input
                        wire:model="url"
                        type="url"
                        required
                        placeholder="Paste a URL (arXiv, Substack, GitHub…)"
                        class="min-w-0 flex-1 border border-edge bg-transparent p-3 text-xs placeholder:text-dim focus:border-accent focus:outline-none"
                    >
                </label>
                <label class="flex items-center gap-2 bg-canvas px-3 lg:w-72">
                    <x-material-icon name="label" class="text-[16px] text-dim" />
                    <input
                        wire:model="tags"
                        type="text"
                        placeholder="#systems #concurrency"
                        class="min-w-0 flex-1 border border-edge bg-transparent p-3 text-xs text-hot placeholder:text-dim focus:border-accent focus:outline-none"
                    >
                </label>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="ingest"
                    class="flex items-center justify-center gap-2 bg-accent px-5 py-3 text-xs font-bold text-canvas hover:brightness-110 disabled:opacity-60"
                >
                    <x-material-icon name="downloading" class="text-[17px]" />
                    Save link
                </button>
            </form>

            @error('url')
                <p class="text-xs text-hot" role="alert">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2 text-xs text-dim">
                <p>Shortcuts: <kbd class="bg-canvas px-1 text-muted">/</kbd> find</p>
                <p class="flex items-center gap-2">Save from any page: drag @include('partials.bookmarklet') to your bookmarks bar</p>
            </div>
        </section>
    @endcan

    @if ($pipeline->isNotEmpty())
        <section class="flex flex-col gap-4 bg-panel p-5">
            <div class="flex items-center gap-2">
                <span class="h-2 w-1 bg-accent"></span>
                <h2 class="text-xs font-bold">Live pipeline ({{ $pipeline->count() }})</h2>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($pipeline as $item)
                    @php($failed = $item->status === App\Enums\ArticleStatus::Failed)
                    <div wire:key="pipe-{{ $item->id }}" class="relative flex flex-col justify-between gap-3 bg-canvas p-4 transition-colors hover:bg-raised">
                        <div @class(['absolute inset-x-0 top-0 h-1', 'bg-hot' => $failed, 'animate-pulse bg-accent' => ! $failed])></div>

                        <div class="flex items-center justify-between text-xs">
                            <span @class(['flex items-center gap-2 font-sans', 'text-hot' => $failed, 'text-accent' => ! $failed])>
                                <x-material-icon :name="$statusStyles[$item->status->value]['icon']" @class(['text-[16px]', 'animate-spin' => $item->status === App\Enums\ArticleStatus::Fetching]) />
                                {{ $item->status->label() }}
                            </span>
                            <span class="text-dim">Added {{ $item->created_at->diffForHumans() }}</span>
                        </div>

                        <div class="flex flex-col gap-1">
                            <a href="{{ route('articles.show', $item) }}" wire:navigate class="truncate font-sans text-sm after:absolute after:inset-0">{{ $item->title ?? $item->url }}</a>
                            <p class="truncate text-xs text-dim">
                                {{ $item->domain }}@if ($failed && $item->error) · {{ $item->error }}@endif
                            </p>
                        </div>

                        <div class="flex items-center justify-between border-t border-line pt-2 text-xs">
                            <span class="flex gap-2 text-hot">
                                @foreach ($item->tags as $tag)
                                    <span>#{{ $tag }}</span>
                                @endforeach
                            </span>
                            @if ($failed)
                                @can('update', $item)
                                    <span class="relative z-10 flex items-center gap-2">
                                        <button wire:click="retry({{ $item->id }})" class="bg-[#fff7fb] px-2.5 py-2 text-[11px] font-bold text-hot hover:brightness-95">RETRY NOW</button>
                                        <button wire:click="delete({{ $item->id }})" wire:confirm="Remove this link?" class="text-[11px] text-dim hover:text-fg">Dismiss</button>
                                    </span>
                                @endcan
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="flex flex-col gap-4 bg-panel p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex w-fit bg-canvas p-1 text-xs">
                @foreach (['all' => 'ALL', 'unread' => 'UNREAD', 'archived' => 'ARCHIVED'] as $key => $label)
                    <button
                        wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'flex items-center gap-2 px-4 py-1.5',
                            'bg-accent font-bold text-canvas' => $tab === $key,
                            'text-muted hover:text-fg' => $tab !== $key,
                        ])
                    >
                        {{ $label }}
                        <span @class(['px-1.5 text-[10px]', 'bg-white/70 font-bold' => $tab === $key, 'bg-panel' => $tab !== $key, 'text-hot' => $tab !== $key && $key === 'unread'])>{{ $tabCounts[$key] }}</span>
                    </button>
                @endforeach
            </div>

            <label class="flex items-center gap-2 bg-canvas px-3 py-1.5 text-xs">
                <span class="text-dim">SORT:</span>
                <select wire:model.live="sort" class="border border-edge bg-canvas py-2 pl-3 pr-8 text-accent focus:border-accent focus:outline-none">
                    <option value="newest">Saved (newest)</option>
                    <option value="oldest">Saved (oldest)</option>
                    <option value="title">Title (A–Z)</option>
                </select>
            </label>
        </div>

        <div class="flex flex-col gap-3 border-t border-line pt-3 text-xs lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-dim">Status:</span>
                <button wire:click="$set('status', '')" @class(['bg-canvas px-2.5 py-2', 'text-fg ring-1 ring-accent' => $status === '', 'text-fg' => $status !== ''])>All ({{ $tabCounts['all'] }})</button>
                @foreach ([['ready', 'Ready', 'bg-ok', 'text-ok'], ['queued', 'Queued', 'bg-accent', 'text-accent'], ['failed', 'Failed', 'bg-hot', 'text-hot']] as [$key, $label, $dot, $text])
                    <button wire:click="$set('status', '{{ $key }}')" @class(['flex items-center gap-1.5 bg-canvas px-2.5 py-2', $text, 'ring-1 ring-accent' => $status === $key])>
                        <span class="size-1.5 {{ $dot }}"></span>
                        {{ $label }} ({{ $statusCounts[$key] ?? 0 }})
                    </button>
                @endforeach
            </div>

            @can('update', new App\Models\Article)
                <div class="flex items-center gap-2">
                    <button wire:click="markVisibleRead" class="flex items-center gap-1.5 bg-canvas px-3 py-2 text-muted hover:text-fg">
                        <x-material-icon name="done_all" class="text-[15px]" /> Mark read
                    </button>
                    <button wire:click="retryFailed" class="flex items-center gap-1.5 bg-canvas px-3 py-2 text-hot hover:brightness-110">
                        <x-material-icon name="replay" class="text-[15px]" /> Retry failed
                    </button>
                </div>
            @endcan
        </div>
    </section>

    <section class="flex flex-col gap-4">
        <div class="flex items-center justify-between px-1 text-xs">
            <h2 class="text-sm font-bold">
                Articles
                @if (trim($search) !== '')
                    <span class="font-normal text-accent">matching “{{ $search }}”</span>
                @endif
            </h2>
            <span class="text-dim">
                @if ($articles->total() > 0)
                    Showing {{ $articles->firstItem() }} – {{ $articles->lastItem() }} of {{ $articles->total() }}
                @endif
            </span>
        </div>

        <div class="flex flex-col gap-3.5">
            @forelse ($articles as $article)
                @php($style = $statusStyles[$article->status->value])
                <article wire:key="article-{{ $article->id }}" class="relative flex flex-col justify-between gap-6 bg-panel p-6 transition-colors hover:bg-raised">
                    <div class="flex gap-4">
                        <div class="flex size-10 shrink-0 items-center justify-center bg-canvas">
                            <x-material-icon :name="$style['icon']" @class(['text-[20px]', $style['color']]) />
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="bg-canvas px-2 py-0.5 font-sans uppercase text-accent">{{ $article->domain }}</span>
                                @if ($article->path())
                                    <span class="truncate text-muted">{{ $article->path() }}</span>
                                @endif
                                @if ($article->readMinutes())
                                    <span class="text-line">·</span>
                                    <span class="text-dim">{{ $article->readMinutes() }} min read ({{ number_format($article->word_count) }} words)</span>
                                @endif
                                <span class="text-line">·</span>
                                <span class="text-dim">Saved {{ $article->created_at->diffForHumans() }}</span>
                            </div>

                            <h3 class="font-sans text-lg leading-snug">
                                {{-- Stretched link: the whole card opens the reader; buttons sit above it. --}}
                                <a href="{{ route('articles.show', $article) }}" wire:navigate class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-accent">{{ $article->title ?? $article->url }}</a>
                            </h3>

                            @if ($article->excerpt)
                                <p class="font-sans text-sm text-muted">{{ $article->excerpt }}</p>
                            @elseif ($article->status === App\Enums\ArticleStatus::Failed && $article->error)
                                <p class="text-xs text-hot">{{ $article->error }}</p>
                            @endif

                            @if ($article->tags)
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    @foreach ($article->tags as $tag)
                                        <span class="bg-canvas px-2 py-0.5 text-hot">#{{ $tag }}</span>
                                    @endforeach
                                    @if ($article->highlights_count)
                                        <span class="text-[11px] text-dim">{{ $article->highlights_count }} {{ Str::plural('highlight', $article->highlights_count) }}</span>
                                    @endif
                                </div>
                            @elseif ($article->highlights_count)
                                <span class="text-[11px] text-dim">{{ $article->highlights_count }} {{ Str::plural('highlight', $article->highlights_count) }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="relative z-10 flex w-fit items-center gap-2" x-data="{ copied: false }">
                        <a href="{{ route('articles.show', $article) }}" wire:navigate class="flex h-8 items-center gap-1.5 bg-accent px-4 text-xs font-bold text-canvas hover:brightness-110">
                            <x-material-icon name="chrome_reader_mode" class="text-[16px]" /> READ
                        </a>
                        <a href="{{ $article->url }}" target="_blank" rel="noopener noreferrer" title="Open original" aria-label="Open original" class="flex size-9 items-center justify-center bg-canvas text-muted hover:text-fg">
                            <x-material-icon name="open_in_new" class="text-[17px]" />
                        </a>
                        <button
                            type="button"
                            title="Copy link"
                            aria-label="Copy link"
                            @click="navigator.clipboard.writeText(@js($article->url)); copied = true; setTimeout(() => copied = false, 1500)"
                            class="flex size-9 items-center justify-center bg-canvas text-muted hover:text-fg"
                        >
                            <x-material-icon x-show="!copied" name="content_copy" class="text-[17px]" />
                            <x-material-icon x-show="copied" x-cloak name="check" class="text-[17px] text-ok" />
                        </button>
                        @can('update', $article)
                            <button wire:click="toggleArchive({{ $article->id }})" title="{{ $article->archived_at ? 'Unarchive' : 'Archive' }}" aria-label="{{ $article->archived_at ? 'Unarchive' : 'Archive' }}" class="flex size-9 items-center justify-center bg-canvas text-muted hover:text-fg">
                                <x-material-icon :name="$article->archived_at ? 'unarchive' : 'inventory_2'" class="text-[17px]" />
                            </button>
                            <button wire:click="retry({{ $article->id }})" title="Fetch again" aria-label="Fetch again" class="flex size-9 items-center justify-center bg-canvas text-muted hover:text-fg">
                                <x-material-icon name="sync" class="text-[17px]" />
                            </button>
                        @endcan
                        @can('delete', $article)
                            <button wire:click="delete({{ $article->id }})" wire:confirm="Delete this article?" title="Delete" aria-label="Delete" class="flex size-9 items-center justify-center bg-canvas text-muted hover:text-hot">
                                <x-material-icon name="delete" class="text-[17px]" />
                            </button>
                        @endcan
                    </div>
                </article>
            @empty
                <div class="bg-panel p-10 text-center text-sm text-dim">
                    @if ($tabCounts['all'] === 0)
                        Nothing saved yet. Paste a link above to get started.
                    @else
                        No articles match this view.
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    <footer class="flex flex-col items-center justify-between gap-3 bg-panel p-4 text-xs lg:flex-row">
        <div class="flex items-center gap-2">
            <span class="size-1.5 bg-ok"></span>
            <span class="font-sans">{{ $tabCounts['all'] }} TOTAL {{ Str::upper(Str::plural('ITEM', $tabCounts['all'])) }}</span>
        </div>
        {{ $articles->links('pagination.terminal') }}
    </footer>
</div>
