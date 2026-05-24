{{-- Drag to the bookmarks bar. Clicking it here does nothing (it would save this page). --}}
<a
    href="javascript:(()=>{window.open('{{ route('save') }}?url='+encodeURIComponent(location.href)+'&amp;title='+encodeURIComponent(document.title),'stash-save','width=520,height=480')})()"
    @click.prevent
    draggable="true"
    title="Drag me to your bookmarks bar"
    class="inline-flex cursor-grab items-center gap-1.5 bg-raised px-2.5 py-1 text-accent hover:brightness-125"
>
    <x-material-icon name="bookmark_add" class="text-[14px]" /> Save to {{ config('app.name') }}
</a>
