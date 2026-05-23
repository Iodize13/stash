@php
    $bars = ['cyan' => 'border-accent', 'pink' => 'border-hot', 'green' => 'border-ok', 'amber' => 'border-amber-400'];
    $fills = ['cyan' => 'bg-accent', 'pink' => 'bg-hot', 'green' => 'bg-ok', 'amber' => 'bg-amber-400'];
    $texts = ['cyan' => 'text-accent', 'pink' => 'text-hot', 'green' => 'text-ok', 'amber' => 'text-amber-400'];
    $percent = fn (int $part) => $stats['total'] ? round($part / $stats['total'] * 100) : 0;
@endphp

<div class="flex flex-col gap-6">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-col gap-2">
            <p class="text-xs text-accent">{{ number_format($stats['total']) }} {{ Str::plural('HIGHLIGHT', $stats['total']) }} ACROSS {{ $stats['articles'] }} {{ Str::plural('ARTICLE', $stats['articles']) }}</p>
            <h1 class="font-sans text-3xl font-semibold">All Highlights &amp; Notes</h1>
        </div>
        <a href="{{ route('highlights.export') }}" class="flex w-fit items-center gap-2 bg-accent px-4 py-2.5 text-xs font-bold text-canvas hover:brightness-110">
            <x-material-icon name="download" class="text-[16px]" /> Export all (.md)
        </a>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex flex-col gap-3 bg-panel p-5">
            <p class="flex items-center justify-between text-xs text-dim">TOTAL HIGHLIGHTS <x-material-icon name="border_color" class="text-[16px] text-accent" /></p>
            <p class="flex items-baseline gap-3">
                <span class="font-sans text-4xl font-semibold">{{ number_format($stats['total']) }}</span>
                <span class="text-xs text-ok">+{{ $stats['thisWeek'] }} this week</span>
            </p>
            <div class="flex h-1 w-full overflow-hidden bg-line" aria-hidden="true">
                @foreach ($stats['colors'] as $swatch => $count)
                    <span class="{{ $fills[$swatch] }}" style="width: {{ $percent($count) }}%"></span>
                @endforeach
            </div>
            <p class="flex flex-wrap gap-x-3 text-[11px]">
                @foreach ($stats['colors'] as $swatch => $count)
                    <span class="{{ $texts[$swatch] }}">{{ $count }} {{ ucfirst($swatch) }}</span>
                @endforeach
            </p>
        </div>
        <div class="flex flex-col gap-3 bg-panel p-5">
            <p class="flex items-center justify-between text-xs text-dim">WITH PERSONAL NOTES <x-material-icon name="sticky_note_2" class="text-[16px] text-amber-400" /></p>
            <p class="flex items-baseline gap-3">
                <span class="font-sans text-4xl font-semibold">{{ number_format($stats['withNotes']) }}</span>
                <span class="text-xs text-muted">{{ $percent($stats['withNotes']) }}% of highlights</span>
            </p>
        </div>
        <div class="flex flex-col gap-3 bg-panel p-5">
            <p class="flex items-center justify-between text-xs text-dim">ARTICLES HIGHLIGHTED <x-material-icon name="auto_stories" class="text-[16px] text-ok" /></p>
            <p class="flex items-baseline gap-3">
                <span class="font-sans text-4xl font-semibold">{{ number_format($stats['articles']) }}</span>
                @if ($stats['articles'])
                    <span class="text-xs text-muted">{{ round($stats['total'] / $stats['articles'], 1) }} per article</span>
                @endif
            </p>
        </div>
        <div class="flex flex-col gap-3 bg-panel p-5">
            <p class="flex items-center justify-between text-xs text-dim">TAGGED <x-material-icon name="sell" class="text-[16px] text-hot" /></p>
            <p class="flex items-baseline gap-3">
                <span class="font-sans text-4xl font-semibold">{{ number_format($stats['tagged']) }}</span>
                <span class="text-xs text-muted">{{ $stats['total'] - $stats['tagged'] }} untagged</span>
            </p>
        </div>
    </section>

    <section class="flex flex-col gap-4 bg-panel p-4 text-xs">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <label class="flex flex-1 items-center gap-2 bg-canvas px-3 py-1.5">
                <x-material-icon name="search" class="text-[17px] text-dim" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Filter by quoted text, note, tag or article title…"
                    class="min-w-0 flex-1 border border-edge bg-transparent px-3 py-2 text-fg placeholder:text-dim focus:border-accent focus:outline-none"
                >
            </label>
            <div class="flex items-center gap-2" role="group" aria-label="Filter by color">
                <span class="text-dim">PALETTE:</span>
                <button type="button" wire:click="$set('color', '')" @class(['px-2 py-1', 'bg-canvas text-fg ring-1 ring-accent' => $color === '', 'text-muted hover:text-fg' => $color !== ''])>ALL</button>
                @foreach ($fills as $value => $fill)
                    <button type="button" wire:click="$set('color', '{{ $value }}')" @class(['size-5', $fill, 'ring-2 ring-fg ring-offset-2 ring-offset-panel' => $color === $value]) aria-label="{{ ucfirst($value) }} highlights"></button>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-3 border-t border-line pt-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-1">
                @foreach (['all' => 'All ('.$stats['total'].')', 'notes' => 'With notes ('.$stats['withNotes'].')', 'untagged' => 'Untagged ('.($stats['total'] - $stats['tagged']).')'] as $key => $label)
                    <button type="button" wire:click="$set('show', '{{ $key }}')" @class(['px-3 py-1.5', 'bg-raised font-bold text-accent' => $show === $key, 'text-muted hover:text-fg' => $show !== $key])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex bg-canvas p-0.5">
                    @foreach (['article' => 'By article', 'time' => 'Chronological'] as $key => $label)
                        <button type="button" wire:click="$set('group', '{{ $key }}')" @class(['px-3 py-1.5', 'bg-raised text-accent' => $group === $key, 'text-muted hover:text-fg' => $group !== $key])>{{ $label }}</button>
                    @endforeach
                </div>
                <label class="flex items-center gap-2">
                    <span class="text-dim">SORT:</span>
                    <select wire:model.live="sort" class="border border-edge bg-canvas py-2 pl-3 pr-8 text-fg focus:border-accent focus:outline-none">
                        <option value="newest">Newest highlighted</option>
                        <option value="oldest">Oldest highlighted</option>
                    </select>
                </label>
            </div>
        </div>
    </section>

    <div class="flex flex-col gap-3">
        @forelse ($sections as $articleId => $items)
            @if ($group === 'article')
                @php($article = $items->first()->article)
                @php($counts = $perArticle[$articleId] ?? null)
                <header wire:key="group-{{ $articleId }}" class="mt-3 flex flex-col gap-3 bg-raised p-4 first:mt-0 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-col gap-1">
                        <p class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="bg-canvas px-2 py-0.5 uppercase text-accent">{{ $article->domain }}</span>
                            @if ($article->byline)
                                <span class="text-dim">· {{ $article->byline }}</span>
                            @endif
                            <span class="text-ok">· Saved {{ $article->created_at->diffForHumans() }}</span>
                        </p>
                        <h2 class="truncate font-sans text-lg font-semibold">{{ $article->title ?? $article->url }}</h2>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 text-xs text-dim">
                        @if ($counts)
                            <span>{{ $counts->highlights_count }} {{ Str::plural('highlight', $counts->highlights_count) }} · {{ $counts->notes_count }} {{ Str::plural('note', $counts->notes_count) }}</span>
                        @endif
                        <a href="{{ route('articles.show', $article) }}" wire:navigate class="flex items-center gap-1 bg-canvas px-3 py-1.5 text-accent hover:brightness-125">
                            Open in reader <x-material-icon name="arrow_forward" class="text-[14px]" />
                        </a>
                    </div>
                </header>
            @endif

            @foreach ($items as $highlight)
                <article
                    wire:key="hl-{{ $highlight->id }}"
                    x-data="{ editing: false, draft: @js($highlight->note ?? ''), copied: false }"
                    class="flex flex-col gap-4 border-l-4 bg-panel p-5 {{ $bars[$highlight->color->value] }}"
                >
                    <div class="flex items-start justify-between gap-3 text-xs">
                        <p class="flex flex-wrap items-center gap-2 text-dim">
                            @if ($group === 'time')
                                <span class="{{ $texts[$highlight->color->value] }}">{{ Str::limit($highlight->article->title ?? $highlight->article->domain, 60) }}</span>
                                <span>·</span>
                            @endif
                            <span title="{{ $highlight->created_at->toDayDateTimeString() }}">{{ $highlight->created_at->diffForHumans() }}</span>
                        </p>
                        <div class="flex shrink-0 items-center gap-2 text-muted">
                            <a href="{{ route('articles.show', $highlight->article_id) }}#hl-{{ $highlight->id }}" class="hover:text-fg" title="Show in article" aria-label="Show in article">
                                <x-material-icon name="chrome_reader_mode" class="text-[16px]" />
                            </a>
                            <button type="button" @click="navigator.clipboard.writeText(@js($highlight->exact)); copied = true; setTimeout(() => copied = false, 1500)" class="hover:text-fg" title="Copy quote" aria-label="Copy quote">
                                <x-material-icon x-show="!copied" name="content_copy" class="text-[16px]" />
                                <x-material-icon x-show="copied" x-cloak name="check" class="text-[16px] text-ok" />
                            </button>
                            @can('update', $highlight)
                                <button type="button" @click="editing = !editing" class="hover:text-fg" title="Edit note" aria-label="Edit note">
                                    <x-material-icon name="edit_note" class="text-[18px]" />
                                </button>
                            @endcan
                            @can('delete', $highlight)
                                <button type="button" wire:click="delete({{ $highlight->id }})" wire:confirm="Delete this highlight?" class="hover:text-hot" title="Delete" aria-label="Delete highlight">
                                    <x-material-icon name="delete" class="text-[16px]" />
                                </button>
                            @endcan
                        </div>
                    </div>

                    <blockquote class="border-l border-line pl-4 font-sans text-lg leading-relaxed text-fg/90">“{{ $highlight->exact }}”</blockquote>

                    @if ($highlight->note)
                        <div x-show="!editing" class="flex flex-col gap-1 bg-canvas p-4">
                            <p class="flex items-center gap-1.5 text-xs {{ $texts[$highlight->color->value] }}"><x-material-icon name="edit" class="text-[13px]" /> NOTE</p>
                            <p class="font-sans text-[15px] leading-relaxed text-muted">{{ $highlight->note }}</p>
                        </div>
                    @endif

                    @can('update', $highlight)
                        <form x-show="editing" x-cloak @submit.prevent="$wire.updateNote({{ $highlight->id }}, draft).then(() => editing = false)" class="flex flex-col gap-2">
                            <textarea x-model="draft" rows="3" placeholder="Add a note" class="border border-edge bg-canvas p-3 font-sans text-sm text-fg focus:border-accent focus:outline-none"></textarea>
                            <div class="flex justify-end gap-2 text-xs">
                                <button type="button" @click="editing = false" class="px-2 py-1 text-dim hover:text-fg">CANCEL</button>
                                <button type="submit" class="bg-accent px-3 py-1.5 font-bold text-canvas">SAVE</button>
                            </div>
                        </form>
                    @endcan

                    @if ($highlight->tags)
                        <div class="flex flex-wrap gap-2 text-xs">
                            @foreach ($highlight->tags as $tag)
                                <button type="button" wire:click="$set('search', @js($tag))" class="bg-canvas px-2 py-0.5 {{ $texts[$highlight->color->value] }} hover:brightness-125">#{{ $tag }}</button>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        @empty
            <div class="bg-panel p-10 text-center text-sm text-dim">
                @if ($stats['total'] === 0)
                    No highlights yet. Open an article and select a passage to highlight it.
                @else
                    No highlights match these filters.
                @endif
            </div>
        @endforelse
    </div>

    <footer class="flex flex-col items-center justify-between gap-3 text-xs lg:flex-row">
        <p class="flex items-center gap-2 text-muted">
            <span class="size-1.5 bg-ok"></span>
            @if ($highlights->total())
                Showing {{ $highlights->firstItem() }}–{{ $highlights->lastItem() }} of {{ $highlights->total() }} {{ Str::plural('highlight', $highlights->total()) }}
            @endif
        </p>
        {{ $highlights->links('pagination.terminal') }}
    </footer>
</div>
