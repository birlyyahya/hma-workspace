<?php

use App\Services\CaCache;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    /** @var array<int, array<string, mixed>> Transaksi mentah dompet PL */
    public array $transaksi = [];

    public function mount(CaCache $ca): void
    {
        $this->load($ca);
    }

    #[On('transaksi-added')]
    public function load(CaCache $ca): void
    {
        $dompetPl = $ca->dompetPl((int) Auth::id());

        if (empty($dompetPl)) {
            return;
        }

        $this->transaksi = $ca->transaksi($dompetPl['kode_ca']);
    }

    public function resetDate(): void
    {
        $this->reset(['start_date', 'end_date']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredTransaksiProperty(): array
    {
        return collect($this->transaksi)
            ->when($this->search !== '', fn ($items) => $items->filter(
                fn (array $trx): bool => str_contains(
                    strtolower($trx['deskripsi'] ?? ''),
                    strtolower($this->search)
                )
            ))
            ->when($this->start_date, fn ($items) => $items->filter(
                fn (array $trx): bool => $trx['tanggal'] >= $this->start_date
            ))
            ->when($this->end_date, fn ($items) => $items->filter(
                fn (array $trx): bool => $trx['tanggal'] <= $this->end_date
            ))
            ->values()
            ->all();
    }
}; ?>

<div class="space-y-4">
    <livewire:cashadvance.widget.ca-widget />

    <div class="bg-white">
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <flux:heading class="font-bold">Recent Transaction</flux:heading>
            <div class="flex gap-4">
                <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="Search"></flux:input>
                <flux:dropdown position="top" align="start">
                    <flux:button icon="calendar" iconClasses="w-4 h-4" class="font-light" variant="outline"></flux:button>
                    <flux:menu>
                        <flux:menu.item disabled>
                            <flux:input label="Start Date" wire:model.live='start_date' type="date"></flux:input>
                        </flux:menu.item>
                        <flux:menu.item disabled>
                            <flux:input label="End Date" wire:model.live='end_date' type="date"></flux:input>
                        </flux:menu.item>
                        <flux:menu.item>
                            <flux:button class="w-full" size="sm" wire:click="resetDate">Reset</flux:button>
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <flux:button icon="arrow-down-tray">Download CSV</flux:button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[900px] md:min-w-full text-sm text-left text-gray-600 ">
                <thead class="bg-zinc-50 text-xs uppercase shadow-sm text-gray-500 ">
                    <tr>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Deskripsi</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Tanggal</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Jenis</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Jumlah</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Saldo Setelah</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Bukti</th>
                    </tr>
                </thead>
                <tbody wire:loading.class="pointer-events-none">
                    @forelse ($this->filteredTransaksi as $trx)
                    <tr wire:key="trx-{{ $trx['id'] }}" class="border-t">
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $trx['deskripsi'] ?? '-' }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                            <flux:badge size="sm" :color="($trx['jenis'] ?? '') === 'penerimaan' ? 'green' : 'red'">
                                {{ ucfirst($trx['jenis'] ?? '-') }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap">
                            Rp {{ number_format((float) ($trx['jumlah'] ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap">
                            Rp {{ number_format((float) ($trx['saldo_setelah'] ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap">
                            @if (! empty($trx['bukti_url']))
                                <flux:button :href="$trx['bukti_url']" target="_blank" size="sm" variant="ghost" icon="paper-clip" />
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                            Belum ada transaksi pada dompet PL
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
