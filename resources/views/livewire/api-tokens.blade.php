<div class="flex max-w-4xl flex-col gap-6">
    <section class="flex flex-col gap-2">
        <p class="text-xs text-accent">// SETTINGS</p>
        <h1 class="font-sans text-3xl font-semibold">API tokens</h1>
        <p class="max-w-2xl font-sans text-sm leading-relaxed text-muted">
            Tokens let scripts and apps outside the browser save and list links for your account, for example a CLI, an iOS Shortcut or an editor plugin.
            For the browser itself, the bookmarklet needs no token.
        </p>
    </section>

    @if ($plainTextToken)
        <section x-data="{ copied: false }" class="flex flex-col gap-3 border border-ok bg-panel p-5 text-xs">
            <p class="flex items-center gap-2 text-ok"><x-material-icon name="key" class="text-[16px]" /> Copy your new token now. It will not be shown again.</p>
            <div class="flex items-center gap-2">
                <code class="min-w-0 flex-1 overflow-x-auto whitespace-nowrap bg-canvas px-3 py-2.5 text-fg">{{ $plainTextToken }}</code>
                <button type="button" @click="navigator.clipboard.writeText(@js($plainTextToken)); copied = true" class="shrink-0 bg-accent px-3 py-2.5 font-bold text-canvas" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
            <p class="text-dim">Try it:</p>
            <pre class="overflow-x-auto bg-canvas p-3 text-muted">curl -X POST {{ route('api.articles.store') }} \
  -H "Authorization: Bearer {{ $plainTextToken }}" \
  -H "Accept: application/json" \
  -d url=https://example.com/article -d "tags[]=reading"</pre>
            <button type="button" wire:click="dismissToken" class="w-fit text-dim hover:text-fg">I've saved it</button>
        </section>
    @endif

    @if ($canManage)
        <section class="flex flex-col gap-4 bg-panel p-5 text-xs">
            <h2 class="text-sm font-bold">New token</h2>
            <form wire:submit="create" class="flex flex-col gap-4">
                <label class="flex flex-col gap-1.5">
                    <span class="text-dim">Name</span>
                    <input wire:model="name" type="text" placeholder="e.g. laptop CLI" class="border border-edge bg-canvas px-3 py-2.5 text-fg placeholder:text-dim focus:border-accent focus:outline-none">
                    @error('name') <span class="text-hot">{{ $message }}</span> @enderror
                </label>
                <fieldset class="flex flex-col gap-1.5">
                    <legend class="mb-1.5 text-dim">Permissions</legend>
                    @foreach (App\Livewire\ApiTokens::ABILITIES as $ability => $label)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="abilities" value="{{ $ability }}" class="accent-[var(--color-accent)]">
                            <span>{{ $label }}</span> <code class="text-dim">{{ $ability }}</code>
                        </label>
                    @endforeach
                    @error('abilities') <span class="text-hot">{{ $message }}</span> @enderror
                </fieldset>
                <button type="submit" class="flex w-fit items-center gap-2 bg-accent px-4 py-2.5 font-bold text-canvas hover:brightness-110">
                    <x-material-icon name="add" class="text-[16px]" /> Create token
                </button>
            </form>
        </section>
    @else
        <p class="flex items-center gap-2 bg-panel p-4 text-xs text-muted">
            <x-material-icon name="lock" class="text-[16px] text-hot" />
            The demo account is read-only and cannot create tokens.
        </p>
    @endif

    <section class="flex flex-col gap-3">
        <h2 class="text-xs tracking-wider text-dim">// ACTIVE TOKENS</h2>
        @forelse ($tokens as $token)
            <div wire:key="token-{{ $token->id }}" class="flex flex-col gap-2 bg-panel p-4 text-xs sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-1">
                    <p class="font-sans text-sm">{{ $token->name }}</p>
                    <p class="flex flex-wrap gap-2 text-dim">
                        @foreach ($token->abilities as $ability)
                            <code class="bg-canvas px-1.5 text-accent">{{ $ability }}</code>
                        @endforeach
                        <span>· created {{ $token->created_at->diffForHumans() }}</span>
                        <span>· {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }}</span>
                    </p>
                </div>
                @if ($canManage)
                    <button type="button" wire:click="revoke({{ $token->id }})" wire:confirm="Revoke this token? Apps using it will stop working." class="w-fit text-hot hover:brightness-125">Revoke</button>
                @endif
            </div>
        @empty
            <p class="bg-panel p-4 text-xs text-dim">No tokens yet.</p>
        @endforelse
    </section>

    <section class="flex flex-col gap-3 text-xs">
        <h2 class="tracking-wider text-dim">// ENDPOINTS</h2>
        <div class="flex flex-col divide-y divide-line bg-panel">
            @foreach ([
                ['POST', '/api/articles', 'articles:write', 'Save a link. Body: url, optional title, tags[]. Returns 201 and the queued article.'],
                ['GET', '/api/articles', 'articles:read', 'List your articles, newest first. Query: status, per_page (max 100).'],
                ['GET', '/api/articles/{id}', 'articles:read', 'One article, including fetch status and error.'],
            ] as [$method, $path, $ability, $description])
                <div class="flex flex-col gap-1 p-4 sm:flex-row sm:items-baseline sm:gap-4">
                    <code class="w-56 shrink-0"><span class="{{ $method === 'POST' ? 'text-ok' : 'text-accent' }}">{{ $method }}</span> {{ $path }}</code>
                    <span class="font-sans text-sm text-muted">{{ $description }} <code class="text-dim">({{ $ability }})</code></span>
                </div>
            @endforeach
        </div>
        <p class="text-dim">Send <code>Accept: application/json</code>. Limited to 60 requests per minute.</p>
    </section>
</div>
