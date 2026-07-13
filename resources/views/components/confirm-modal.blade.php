@props([
    'name',
    'title' => 'Hapus data ini?',
    'description' => 'Data akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.',
    'confirm',
    'confirmLabel' => 'Hapus',
    'confirmLoadingLabel' => 'Menghapus...',
    'cancelLabel' => 'Batal',
    'variant' => 'danger',
    'icon' => 'trash',
    'width' => 'w-xs sm:w-sm md:w-110',
])

{{--
    Reusable confirmation modal (Flux). Tiap komponen Livewire tetap memegang
    state & method-nya sendiri (mis. pendingDeleteId + deleteXxx), komponen ini
    hanya menyeragamkan tampilan modal konfirmasinya.

    Buka modal dari komponen: Flux::modal('nama')->show();
    atau dari Alpine: $flux.modal('nama').show()

    Penggunaan:
        <x-confirm-modal name="delete-project-modal" confirm="deleteProject"
            title="Hapus proyek ini?">
            Proyek <span class="font-semibold">{{ $name }}</span> akan dihapus permanen.
        </x-confirm-modal>
--}}

<flux:modal name="{{ $name }}" class="{{ $width }}" :dismissible="false">
    <div class="space-y-5">
        <div class="flex items-start gap-4">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-red-100 text-red-600 ring-4 ring-red-50/50 dark:bg-red-500/15">
                <flux:icon :name="$icon" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1 space-y-1">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                <flux:text class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $slot->isEmpty() ? $description : $slot }}
                </flux:text>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
            </flux:modal.close>
            <flux:button
                :variant="$variant"
                :icon="$icon"
                wire:click="{{ $confirm }}"
                wire:loading.attr="disabled"
                wire:target="{{ $confirm }}"
            >
                <span wire:loading.remove wire:target="{{ $confirm }}">{{ $confirmLabel }}</span>
                <span wire:loading wire:target="{{ $confirm }}">{{ $confirmLoadingLabel }}</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
