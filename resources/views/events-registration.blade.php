@include('partials.head')

<div class="bg-zinc-100 h-screen p-10">

    <flux:button variant="ghost" icon="chevron-left" href="{{ route('events') }}">Back to Events</flux:button>

    <x-toaster-hub />

    <livewire:events.registration-qr />
</div>


@fluxScripts
