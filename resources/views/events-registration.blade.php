@include('partials.head')

<div class="min-h-screen bg-linear-to-br from-zinc-50 to-zinc-100">
    <div class="px-6 lg:px-10 py-6">
        <div class="flex items-center justify-between mb-6">
            <flux:button variant="ghost" icon="chevron-left" href="{{ route('events') }}" wire:navigate>
                Back to Events
            </flux:button>
            <div class="flex items-center gap-2 text-xs text-zinc-500">
                <flux:icon.identification class="w-4 h-4" />
                Day-1 Registration
            </div>
        </div>

        <x-toaster-hub />
        <livewire:events.registration-qr />
    </div>
</div>

@fluxScripts
