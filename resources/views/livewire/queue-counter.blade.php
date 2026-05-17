<div wire:poll.3s class="flex items-center gap-2">
    <span @class(['size-2', 'bg-accent' => $count > 0, 'bg-ok' => $count === 0])></span>
    <span class="text-muted">Active queue:</span>
    <span class="font-bold text-accent">{{ $count }} {{ Str::plural('item', $count) }}</span>
</div>
