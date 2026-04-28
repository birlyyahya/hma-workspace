@include('partials.head')

<div class="min-h-screen bg-linear-to-br from-zinc-50 to-zinc-100">
    <div class="px-6 lg:px-10 py-6">
        <div class="flex items-center justify-between mb-6">
            <flux:button variant="ghost" icon="chevron-left" href="{{ route('events') }}" wire:navigate>
                Back to Events
            </flux:button>
            <div class="flex items-center gap-2 text-xs text-zinc-500">
                <span class="flex h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                Kiosk Mode · Check-in
            </div>
        </div>

        <x-toaster-hub />
        <livewire:events.qrscan />
    </div>
</div>

@fluxScripts
