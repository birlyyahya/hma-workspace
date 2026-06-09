<x-layouts.app>
    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-col items-start gap-1 md:flex-row md:items-end md:gap-4">
                    <flux:heading class="font-bold text-xl">Cash Advance</flux:heading>
                    <flux:text>Kelola dompet CA PL & dompet kegiatan Anda</flux:text>
                </div>

                <flux:modal.trigger name="riwayat-periode">
                    <flux:button size="sm" variant="outline" icon="clock">Riwayat Periode</flux:button>
                </flux:modal.trigger>
            </div>
            <livewire:cashadvance.ca-index lazy />
        </div>
    </div>
</x-layouts.app>
