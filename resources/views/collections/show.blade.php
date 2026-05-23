@php
    $bars = ['cyan' => 'bg-accent', 'pink' => 'bg-hot', 'green' => 'bg-ok', 'amber' => 'bg-amber-400'];
    $tagColors = ['text-accent', 'text-ok', 'text-hot'];
@endphp

<x-layouts.public
    :title="$collection->title"
    :description="$collection->description"
    :feed="route('collections.feed', $collection)"
>
    <div x-data="{ topic: null, q: '', copied: false }">
        <header class="border-b border-line bg-rail">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-6 items-center justify-center bg-accent text-[11px] font-bold text-canvas">{{ strtoupper(substr(config('app.name'), 0, 2)) }}</span>
                    <span class="text-sm font-bold">{{ config('app.name') }}</span>
                    <span class="hidden truncate text-dim sm:inline">
                        {{ Str::lower(Str::before($collection->user->name, ' ')) }} / collections / <span class="text-accent">{{ $collection->slug }}</span>
                    </span>
                    <span class="flex items-center gap-1.5 bg-panel px-2 py-0.5 text-ok"><span class="size-1.5 bg-ok"></span>PUBLIC</span>
                </div>
                <nav class="flex items-center gap-1 text-muted">
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText(location.href); copied = true; setTimeout(() => copied = false, 1500)"
                        class="px-2.5 py-1.5 hover:text-fg"
                        x-text="copied ? 'Copied!' : 'Copy link'"
                    >Copy link</button>
                    <a href="{{ route('collections.feed', $collection) }}" class="px-2.5 py-1.5 hover:text-fg">Atom feed</a>
                    <a href="{{ route('collections.export', $collection) }}" class="bg-accent px-3 py-1.5 font-bold text-canvas hover:brightness-110">Download .md</a>
                </nav>
            </div>
        </header>

        <a href="{{ route('home') }}" class="block border-b border-line bg-panel px-4 py-2 text-center text-xs text-muted hover:text-fg">
            This collection was curated with <span class="text-accent">{{ config('app.name') }}</span>, a read-later app with highlights. See how it works →
        </a>

        <main class="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-10 lg:px-8">
            <section class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="flex flex-col gap-4">
                    <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-accent">
                        <span>// PUBLIC COLLECTION</span>
                        <span class="text-muted">{{ request()->getHost() }}/c/{{ $collection->slug }}</span>
                    </p>
                    <h1 class="font-sans text-4xl font-semibold leading-tight lg:text-5xl">{{ $collection->title }}</h1>
                    @if ($collection->description)
                        <p class="max-w-2xl font-sans text-lg leading-relaxed text-muted">{{ $collection->description }}</p>
                    @endif
                    <p class="text-xs text-dim">
                        Curated by <span class="text-fg">{{ $collection->user->name }}</span>
                        · Updated {{ $collection->updated_at->diffForHumans() }}
                    </p>
                </div>

                <dl class="grid grid-cols-3 gap-2 self-start bg-panel p-4 text-xs">
                    <div class="flex flex-col gap-1 bg-canvas p-3">
                        <dt class="order-2 text-dim">SOURCES</dt>
                        <dd class="order-1 text-3xl font-bold text-accent">{{ $collection->articles->count() }}</dd>
                    </div>
                    <div class="flex flex-col gap-1 bg-canvas p-3">
                        <dt class="order-2 text-dim">HIGHLIGHTS</dt>
                        <dd class="order-1 text-3xl font-bold text-hot">{{ $highlightCount }}</dd>
                    </div>
                    <div class="flex flex-col gap-1 bg-canvas p-3">
                        <dt class="order-2 text-dim">NOTES</dt>
                        <dd class="order-1 text-3xl font-bold text-ok">{{ $noteCount }}</dd>
                    </div>
                </dl>
            </section>

            <aside class="flex gap-4 bg-panel p-4 text-xs">
                <x-material-icon name="gavel" class="text-[18px] text-accent" />
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-accent">// QUOTES, NOT COPIES</p>
                    <p class="text-muted">This page publishes the curator's highlights and notes with a link to each source. Full article text is never republished; quotes belong to their original authors.</p>
                </div>
            </aside>

            @if ($collection->articles->isNotEmpty())
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <button type="button" @click="topic = null" :class="topic === null ? 'bg-accent font-bold text-canvas' : 'bg-panel text-muted hover:text-fg'" class="px-3 py-1.5">
                            All topics ({{ $collection->articles->count() }})
                        </button>
                        @foreach ($tags->take(8) as $tag => $count)
                            <button type="button" @click="topic = @js($tag)" :class="topic === @js($tag) ? 'bg-accent font-bold text-canvas' : 'bg-panel text-muted hover:text-fg'" class="px-3 py-1.5">
                                #{{ $tag }} ({{ $count }})
                            </button>
                        @endforeach
                    </div>
                    <label class="flex items-center gap-2 bg-panel px-3 py-1.5 text-xs lg:w-72">
                        <x-material-icon name="search" class="text-[16px] text-dim" />
                        <input x-model.debounce.150ms="q" type="search" placeholder="Search highlights & keywords…" class="min-w-0 flex-1 bg-transparent py-1 placeholder:text-dim focus:outline-none">
                    </label>
                </div>
            @endif

            <div class="flex flex-col gap-6">
                @forelse ($collection->articles as $article)
                    @php
                        $searchable = Str::lower(implode(' ', [$article->title, $article->domain, $article->byline, $article->pivot->note, implode(' ', $article->tags ?? []), $article->highlights->pluck('exact')->implode(' '), $article->highlights->pluck('note')->implode(' ')]));
                        $notes = $article->highlights->whereNotNull('note')->count();
                    @endphp
                    <article
                        x-show="(topic === null || @js($article->tags ?? []).includes(topic)) && (q === '' || @js($searchable).includes(q.toLowerCase()))"
                        class="flex flex-col gap-5 bg-panel p-6 lg:p-8"
                    >
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex flex-col gap-2">
                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                    <span class="bg-canvas px-2 py-0.5 text-accent">// SOURCE</span>
                                    <span class="text-muted">{{ $article->domain }}</span>
                                    <span class="text-line">·</span>
                                    <span class="text-dim">Saved {{ $article->created_at->format('M Y') }}</span>
                                    <span class="text-line">·</span>
                                    <span class="text-ok">{{ $article->highlights->count() }} {{ Str::plural('highlight', $article->highlights->count()) }}</span>
                                    @if ($notes)
                                        <span class="text-line">·</span>
                                        <span class="text-hot">{{ $notes }} {{ Str::plural('note', $notes) }}</span>
                                    @endif
                                </p>
                                <h2 class="font-sans text-2xl font-semibold leading-snug">{{ $article->title ?? $article->url }}</h2>
                                @if ($article->byline)
                                    <p class="text-xs text-dim">{{ $article->byline }}</p>
                                @endif
                            </div>
                            <a href="{{ $article->url }}" target="_blank" rel="noopener noreferrer" class="flex shrink-0 items-center gap-1.5 bg-canvas px-3 py-2 text-xs text-ok hover:brightness-125">
                                Read original at {{ $article->domain }} <x-material-icon name="north_east" class="text-[14px]" />
                            </a>
                        </div>

                        @if ($article->pivot->note)
                            <div class="flex flex-col gap-1.5 bg-raised p-4">
                                <p class="flex items-center gap-2 text-xs text-hot"><x-material-icon name="rate_review" class="text-[14px]" /> // CURATOR NOTE</p>
                                <p class="font-sans text-[15px] leading-relaxed">{{ $article->pivot->note }}</p>
                            </div>
                        @endif

                        @if ($article->highlights->isNotEmpty())
                            <div class="flex flex-col gap-3">
                                <p class="text-xs tracking-wider text-dim">// HIGHLIGHTS</p>
                                @foreach ($article->highlights as $highlight)
                                    <figure class="flex gap-4 bg-canvas p-4">
                                        <span class="w-1 shrink-0 {{ $bars[$highlight->color->value] }}"></span>
                                        <div class="flex flex-col gap-2">
                                            <blockquote class="font-sans text-[15px] italic leading-relaxed text-fg/90">“{{ $highlight->exact }}”</blockquote>
                                            @if ($highlight->note)
                                                <figcaption class="font-sans text-sm text-muted">{{ $highlight->note }}</figcaption>
                                            @endif
                                        </div>
                                    </figure>
                                @endforeach
                            </div>
                        @endif

                        @if ($article->tags)
                            <div class="flex flex-wrap gap-2 text-xs">
                                @foreach ($article->tags as $tag)
                                    <span class="bg-canvas px-2 py-0.5 {{ $tagColors[$loop->index % 3] }}">#{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="bg-panel p-10 text-center text-sm text-dim">This collection is empty for now.</p>
                @endforelse
            </div>
        </main>

        <footer class="border-t border-line bg-rail">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-xs text-dim sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2 hover:text-fg"><span class="size-3 bg-accent"></span> Curated with {{ config('app.name') }} · how it works →</a>
                <span class="flex gap-4">
                    <a href="{{ route('collections.feed', $collection) }}" class="hover:text-fg">Atom feed</a>
                    <a href="{{ route('collections.export', $collection) }}" class="hover:text-fg">Markdown export</a>
                </span>
            </div>
        </footer>
    </div>
</x-layouts.public>
