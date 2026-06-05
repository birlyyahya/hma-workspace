<?php

use App\Services\CaCache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Detail Dompet'])]
class extends Component {
    public string $kodeCa = '';

    /** @var array<string, mixed> Info dompet kegiatan */
    public array $dompet = [];

    /** @var array<int, array<string, mixed>> Daftar transaksi dompet */
    public array $transaksi = [];

    public function mount(string $kodeCa, CaCache $ca): void
    {
        $this->kodeCa = $kodeCa;
        $this->load($ca);
    }

    #[On('transaksi-added')]
    public function load(CaCache $ca): void
    {
        $this->dompet = $ca->dompetByKode((int) Auth::id(), $this->kodeCa);
        $this->transaksi = $ca->transaksi($this->kodeCa);
    }

    public function hapusTransaksi(int $id, CaCache $ca): void
    {
        $response = $ca->deleteTransaksi($id);

        if (! ($response['success'] ?? false)) {
            Toaster::error($response['message'] ?? 'Gagal menghapus transaksi');

            return;
        }

        Toaster::success('Transaksi berhasil dihapus');

        $this->load($ca);
    }
}; ?>

<div class="max-h-screen overflow-auto px-2 py-4">
    <div class="py-4 space-y-4">

        {{-- HEADER --}}
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <flux:button :href="route('cashadvance')" wire:navigate icon="arrow-left" variant="ghost" size="sm" />
                <div>
                    <flux:heading class="font-bold text-xl">
                        {{ $dompet['judul_kegiatan'] ?? $kodeCa }}
                    </flux:heading>
                    <flux:text>{{ $kodeCa }} • Tahun Anggaran {{ $dompet['tahun_anggaran'] ?? '-' }}</flux:text>
                </div>
            </div>

            @if (! empty($dompet))
                <livewire:cashadvance.transaksi-modal :kode-ca="$kodeCa" :key="'trx-' . $kodeCa" />
            @endif
        </div>

        {{-- SUMMARY --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg border p-6">
                <p class="text-sm text-gray-500">Saldo Akhir</p>
                <h3 class="text-2xl font-semibold mt-2">
                    Rp {{ number_format((float) ($dompet['saldo_akhir'] ?? 0), 2, ',', '.') }}
                </h3>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <p class="text-sm text-gray-500">Total Penerimaan</p>
                <h3 class="text-2xl font-semibold mt-2 text-green-600">
                    Rp {{ number_format((float) ($dompet['total_penerimaan'] ?? 0), 2, ',', '.') }}
                </h3>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <p class="text-sm text-gray-500">Total Pengeluaran</p>
                <h3 class="text-2xl font-semibold mt-2 text-red-600">
                    Rp {{ number_format((float) ($dompet['total_pengeluaran'] ?? 0), 2, ',', '.') }}
                </h3>
            </div>
        </div>

        {{-- TRANSAKSI --}}
        <div class="bg-white rounded-lg border">
            <div class="p-4">
                <flux:heading class="font-bold">Riwayat Transaksi</flux:heading>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[800px] md:min-w-full text-sm text-left text-gray-600">
                    <thead class="bg-zinc-50 text-xs uppercase shadow-sm text-gray-500">
                        <tr>
                            <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Tanggal</th>
                            <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Jenis</th>
                            <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Deskripsi</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Jumlah</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Saldo Setelah</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Bukti</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transaksi as $trx)
                        <tr wire:key="trx-{{ $trx['id'] }}" class="border-t">
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                                <flux:badge size="sm" :color="($trx['jenis'] ?? '') === 'penerimaan' ? 'green' : 'red'">
                                    {{ ucfirst($trx['jenis'] ?? '-') }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-3 md:px-6">{{ $trx['deskripsi'] ?? '-' }}</td>
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
                            <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600"
                                    wire:click="hapusTransaksi({{ $trx['id'] }})"
                                    wire:confirm="Hapus transaksi ini?"
                                />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                                Belum ada transaksi pada dompet ini
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
