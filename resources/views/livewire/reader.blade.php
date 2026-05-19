@php
    $swatches = ['cyan' => 'bg-accent', 'pink' => 'bg-hot', 'green' => 'bg-ok', 'amber' => 'bg-amber-400'];
    $labels = ['cyan' => 'text-accent', 'pink' => 'text-hot', 'green' => 'text-ok', 'amber' => 'text-amber-400'];
    $canEdit = auth()->user()->can('update', $article);
    $ready = $article->status === App\Enums\ArticleStatus::Ready && $article->content_html;
@endphp

<div
    x-data="reader({ anchors: @js($anchors), canEdit: @js($canEdit) })"
    @highlights-changed.window="refresh($event.detail.anchors)"
    @scroll.window.throttle.100ms="onScroll()"
    @keydown.escape.window="cancel()"
    class="flex flex-col gap-6"
>
    <div class="fixed inset-x-0 top-0 z-30 h-0.5 bg-line lg:left-64" aria-hidden="true">
        <div class="h-full bg-accent transition-[width]" :style="`width: ${progress}%`"></div>
    </div>

    <section class="flex flex-col gap-4 border-b border-line pb-4 text-xs">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-muted">
            <a href="{{ route('library') }}" wire:navigate class="flex items-center gap-1 uppercase hover:text-fg">
                <x-material-icon name="arrow_back" class="text-[13px]" /> Library
            </a>
            <span class="text-line">/</span>
            <a href="{{ $article->url }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 text-accent hover:underline">
                {{ $article->domain }} <x-material-icon name="open_in_new" class="text-[12px]" />
            </a>
            @if ($article->word_count)
                <span class="text-line">·</span>
                <span>{{ number_format($article->word_count) }} WORDS</span>
                <span class="text-line">·</span>
                <span>{{ $article->readMinutes() }} MIN READ</span>
            @endif
            <span class="text-line">·</span>
            <span>SAVED {{ Str::upper($article->created_at->diffForHumans()) }}</span>
            @if ($ready)
                <span class="text-line">·</span>
                <span class="flex items-center gap-1.5"><span class="size-1.5 bg-ok"></span><span x-text="`${progress}% READ`"></span></span>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1 bg-panel p-0.5" role="group" aria-label="Reading preferences">
                <button type="button" @click="size = Math.max(14, size - 1)" class="px-2.5 py-1.5 text-muted hover:text-fg" aria-label="Smaller text">A-</button>
                <button type="button" @click="size = Math.min(24, size + 1)" class="px-2.5 py-1.5 text-muted hover:text-fg" aria-label="Larger text">A+</button>
                <span class="mx-1 h-3.5 w-px bg-line"></span>
                @foreach (['sans' => 'SANS', 'serif' => 'SERIF', 'mono' => 'MONO'] as $value => $label)
                    <button type="button" @click="family = '{{ $value }}'" :class="family === '{{ $value }}' ? 'bg-raised text-accent' : 'text-muted hover:text-fg'" class="px-2.5 py-1.5">{{ $label }}</button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-1">
                @if ($canEdit)
                    <button type="button" wire:click="toggleRead" class="flex items-center gap-1.5 px-2.5 py-1.5 text-muted hover:text-fg">
                        <x-material-icon :name="$article->read_at ? 'check_box' : 'check_box_outline_blank'" class="text-[14px]" />
                        {{ $article->read_at ? 'READ' : 'MARK READ' }}
                    </button>
                    <button type="button" wire:click="toggleArchive" class="flex items-center gap-1.5 px-2.5 py-1.5 text-muted hover:text-fg">
                        <x-material-icon :name="$article->archived_at ? 'unarchive' : 'archive'" class="text-[14px]" />
                        {{ $article->archived_at ? 'UNARCHIVE' : 'ARCHIVE' }}
                    </button>
                @endif
                <a href="{{ route('articles.highlights.export', $article) }}" class="flex items-center gap-1.5 px-2.5 py-1.5 text-muted hover:text-fg">
                    <x-material-icon name="terminal" class="text-[14px]" /> EXPORT .MD
                </a>
                <button type="button" @click="notesOpen = !notesOpen" :class="notesOpen ? 'bg-raised text-accent' : 'text-muted hover:text-fg'" class="flex items-center gap-1.5 px-2.5 py-1.5">
                    <x-material-icon name="comment" class="text-[14px]" /> NOTES ({{ $highlights->count() }})
                </button>
            </div>
        </div>
    </section>

    @unless ($ready)
        <section class="flex flex-col items-center gap-3 bg-panel p-10 text-center" @if ($article->status->isActive()) wire:poll.3s @endif>
            @if ($article->status === App\Enums\ArticleStatus::Failed)
                <x-material-icon name="warning" class="text-[28px] text-hot" />
                <p class="text-sm">This link could not be fetched.</p>
                <p class="text-xs text-hot">{{ $article->error }}</p>
                @if ($canEdit)
                    <button type="button" wire:click="retry" class="mt-2 bg-[#fff7fb] px-3 py-2 text-[11px] font-bold text-hot">RETRY NOW</button>
                @endif
            @elseif ($article->status->isActive())
                <x-material-icon name="sync" class="animate-spin text-[28px] text-accent" />
                <p class="text-sm">Fetching and extracting this article…</p>
                <p class="text-xs text-dim">This page updates on its own.</p>
            @else
                <x-material-icon name="description" class="text-[28px] text-dim" />
                <p class="text-sm">No readable content was stored for this link.</p>
            @endif
            <a href="{{ $article->url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-accent hover:underline">Open the original page</a>
        </section>
    @else
        <div class="grid gap-8" :class="notesOpen && 'xl:grid-cols-[minmax(0,1fr)_20rem]'">
            <article x-ref="article" class="relative mx-auto w-full max-w-[44rem]">
                <header class="mb-10 flex flex-col gap-4 border-b border-line pb-8">
                    @if ($article->tags)
                        <div class="flex flex-wrap gap-3 text-xs text-hot">
                            @foreach ($article->tags as $tag)
                                <span>#{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                    <h1 class="font-sans text-3xl font-semibold leading-tight lg:text-4xl">{{ $article->title }}</h1>
                    @if ($article->byline)
                        <p class="text-sm text-muted">{{ $article->byline }}</p>
                    @endif
                </header>

                <div
                    x-ref="body"
                    wire:ignore
                    @mouseup="setTimeout(() => capture())"
                    @keyup.shift="capture()"
                    class="reader-body"
                    :class="{ 'font-sans': family === 'sans', 'font-serif': family === 'serif', 'font-mono': family === 'mono' }"
                    :style="`font-size: ${size}px`"
                >{!! $article->content_html !!}</div>

                <div
                    x-show="selection"
                    x-cloak
                    x-transition.opacity
                    @mousedown.stop
                    @mouseup.stop
                    class="absolute z-20 w-[21rem] border border-line bg-rail p-3 text-xs shadow-xl shadow-black/40"
                    :style="`top: ${popover.top}px; left: ${popover.left}px`"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            @foreach ($swatches as $value => $class)
                                <button type="button" @click="color = '{{ $value }}'" :class="color === '{{ $value }}' && 'ring-2 ring-fg ring-offset-2 ring-offset-rail'" class="size-4 {{ $class }}" aria-label="{{ ucfirst($value) }} highlight"></button>
                            @endforeach
                        </div>
                        <button type="button" @click="copySelection()" class="bg-canvas px-2 py-1 text-muted hover:text-fg">COPY</button>
                    </div>
                    <form @submit.prevent="save()" class="mt-2.5 flex flex-col gap-2">
                        <input x-model="note" type="text" placeholder="Add a note (optional)" class="border border-edge bg-canvas px-2.5 py-1.5 text-fg placeholder:text-dim focus:border-accent focus:outline-none">
                        <div class="flex items-center gap-2">
                            <input x-model="tags" type="text" placeholder="#tags" class="min-w-0 flex-1 border border-edge bg-canvas px-2.5 py-1.5 text-hot placeholder:text-dim focus:border-accent focus:outline-none">
                            <button type="submit" class="flex items-center gap-1 bg-accent px-3 py-1.5 font-bold text-canvas">SAVE <span class="text-[10px]">⏎</span></button>
                        </div>
                    </form>
                    @error('highlight')
                        <p class="mt-2 text-hot">{{ $message }}</p>
                    @enderror
                </div>

                <footer class="mt-16 flex flex-col gap-4 border border-line bg-panel p-6 text-xs sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold">END OF ARTICLE</p>
                        <p class="mt-1 text-muted">{{ $highlights->count() }} {{ Str::plural('highlight', $highlights->count()) }} saved from this page.</p>
                    </div>
                    @if ($next)
                        <a href="{{ route('articles.show', $next) }}" wire:navigate class="flex max-w-xs items-center gap-2 text-right hover:text-accent">
                            <span class="truncate">NEXT: {{ $next->title }}</span>
                            <x-material-icon name="arrow_forward" class="text-[16px]" />
                        </a>
                    @endif
                </footer>
            </article>

            <aside x-show="notesOpen" class="flex flex-col gap-4 xl:sticky xl:top-6 xl:max-h-[calc(100vh-3rem)] xl:self-start xl:overflow-y-auto">
                <div class="flex items-center justify-between gap-2 bg-panel p-4">
                    <h2 class="text-sm font-bold">Highlights <span class="text-accent">{{ $highlights->count() }}</span></h2>
                    <div class="flex items-center gap-1" role="group" aria-label="Filter by color">
                        @foreach ($swatches as $value => $class)
                            <button type="button" @click="filter = filter === '{{ $value }}' ? null : '{{ $value }}'" :class="filter === '{{ $value }}' && 'ring-2 ring-fg ring-offset-1 ring-offset-panel'" class="size-3.5 {{ $class }}" aria-label="Show {{ $value }} highlights"></button>
                        @endforeach
                        <button type="button" @click="filter = null" :class="filter === null ? 'text-fg' : 'text-dim'" class="ml-1 bg-canvas px-1.5 py-0.5 text-[10px]">ALL</button>
                    </div>
                </div>

                @forelse ($highlights as $highlight)
                    <div
                        wire:key="hl-{{ $highlight->id }}"
                        x-show="filter === null || filter === '{{ $highlight->color->value }}'"
                        x-data="{ editing: false, draft: @js($highlight->note ?? '') }"
                        class="flex flex-col gap-3 bg-panel p-4 text-xs"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 {{ $labels[$highlight->color->value] }}">
                                <span class="size-2 {{ $swatches[$highlight->color->value] }}"></span>
                                HIGHLIGHT
                            </span>
                            <span class="text-dim">{{ Str::upper($highlight->created_at->diffForHumans(short: true)) }}</span>
                        </div>

                        <blockquote class="font-sans text-sm italic leading-relaxed text-fg/90">“{{ Str::limit($highlight->exact, 280) }}”</blockquote>

                        @if ($highlight->note)
                            <div x-show="!editing" class="border-l border-line pl-3">
                                <p class="text-[10px] text-dim">YOUR NOTE</p>
                                <p class="mt-1 font-sans text-sm text-muted">{{ $highlight->note }}</p>
                            </div>
                        @endif

                        @if ($canEdit)
                            <form x-show="editing" x-cloak @submit.prevent="$wire.updateNote({{ $highlight->id }}, draft).then(() => editing = false)" class="flex flex-col gap-2">
                                <textarea x-model="draft" rows="3" class="border border-edge bg-canvas p-2 font-sans text-sm text-fg focus:border-accent focus:outline-none"></textarea>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="editing = false" class="px-2 py-1 text-dim hover:text-fg">CANCEL</button>
                                    <button type="submit" class="bg-accent px-2.5 py-1 font-bold text-canvas">SAVE</button>
                                </div>
                            </form>
                        @endif

                        <div class="flex items-center justify-between gap-2">
                            <span class="flex flex-wrap gap-2 text-hot">
                                @foreach ($highlight->tags as $tag)
                                    <span>#{{ $tag }}</span>
                                @endforeach
                            </span>
                            <span class="flex shrink-0 items-center gap-2 text-muted">
                                <button type="button" x-show="!missing.includes({{ $highlight->id }})" @click="jump({{ $highlight->id }})" class="flex items-center gap-1 hover:text-fg">
                                    <x-material-icon name="my_location" class="text-[13px]" /> JUMP
                                </button>
                                <span x-show="missing.includes({{ $highlight->id }})" x-cloak class="text-dim" title="This passage was not found in the stored text">NOT FOUND</span>
                                <button type="button" @click="navigator.clipboard.writeText(@js($highlight->exact))" class="hover:text-fg" aria-label="Copy quote">
                                    <x-material-icon name="content_copy" class="text-[14px]" />
                                </button>
                                @if ($canEdit)
                                    <button type="button" @click="editing = !editing" class="hover:text-fg" aria-label="Edit note">
                                        <x-material-icon name="edit_note" class="text-[16px]" />
                                    </button>
                                    <button type="button" wire:click="deleteHighlight({{ $highlight->id }})" wire:confirm="Delete this highlight?" class="hover:text-hot" aria-label="Delete highlight">
                                        <x-material-icon name="delete" class="text-[14px]" />
                                    </button>
                                @endif
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="bg-panel p-4 text-xs text-dim">
                        @if ($canEdit)
                            Select any passage in the article to highlight it.
                        @else
                            No highlights on this article yet.
                        @endif
                    </p>
                @endforelse

                <a href="{{ route('articles.highlights.export', $article) }}" class="flex items-center justify-center gap-2 border border-line p-3 text-xs text-muted hover:text-fg">
                    <x-material-icon name="download_for_offline" class="text-[15px]" /> EXPORT HIGHLIGHTS (.MD)
                </a>
            </aside>
        </div>
    @endunless
</div>
