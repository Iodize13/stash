@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center gap-1 bg-canvas p-1 text-xs">
        <button
            type="button"
            wire:click="previousPage('{{ $paginator->getPageName() }}')"
            @disabled($paginator->onFirstPage())
            aria-label="Previous page"
            class="px-2.5 py-1 text-dim enabled:hover:text-fg disabled:opacity-40"
        >
            <x-material-icon name="chevron_left" class="text-[16px]" />
        </button>

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-dim">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page" class="bg-accent px-3 py-1 font-bold text-canvas">{{ $page }}</span>
                    @else
                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="px-3 py-1 text-muted hover:text-fg">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        <button
            type="button"
            wire:click="nextPage('{{ $paginator->getPageName() }}')"
            @disabled(! $paginator->hasMorePages())
            aria-label="Next page"
            class="px-2.5 py-1 text-dim enabled:hover:text-fg disabled:opacity-40"
        >
            <x-material-icon name="chevron_right" class="text-[16px]" />
        </button>
    </nav>
@endif
