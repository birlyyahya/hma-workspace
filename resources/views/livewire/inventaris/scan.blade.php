<?php

use App\Services\InventarisDummy;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Scan'])]
class extends Component {
    public string $query = '';

    /** Hasil lookup terakhir (null = belum / tidak ketemu). */
    public ?array $found = null;

    public bool $searched = false;

    /** Simulasi: anggap pengguna BUKAN anggota tim project (untuk melihat state terkunci). */
    public bool $simulateOutsider = false;

    /** Form assign untuk unit kosong. */
    public string $assignSn = '';

    public string $assignDestination = '';

    public function lookup(): void
    {
        $this->searched = true;
        $this->reset('assignSn', 'assignDestination');
        $this->found = app(InventarisDummy::class)->findBySnOrCode($this->query);
    }

    public function complete(): void
    {
        if ($this->found === null) {
            return;
        }

        $result = app(InventarisDummy::class)->assignSn($this->found['id'], $this->assignSn, $this->assignDestination);

        if (! $result['ok']) {
            Toaster::warning($result['message']);

            return;
        }

        Toaster::success($result['message']);
        $this->reset('assignSn', 'assignDestination');
        $this->found = app(InventarisDummy::class)->unit($this->found['id']);
    }

    public function statusLabel(string $status): string
    {
        return InventarisDummy::statusLabel($status);
    }

    public function statusColor(string $status): string
    {
        return InventarisDummy::statusColor($status);
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-sm mx-auto px-4 sm:px-6 py-6 space-y-5" x-data="{ tab: 'manual' }">

        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Scan</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div>
            <flux:heading size="lg">Scan Unit</flux:heading>
            <flux:text size="sm" class="mt-0.5">Scan QR label unit atau ketik kode/SN untuk mencari.</flux:text>
        </div>

        {{-- ATURAN SCAN --}}
        <flux:callout icon="information-circle" color="zinc">
            <flux:callout.text>
                Unit <strong>belum lengkap</strong> (tanpa SN): anggota tim project langsung dapat form pengisian; pengguna lain hanya melihat status.
                Unit <strong>sudah lengkap</strong>: siapa pun melihat detail penuh.
            </flux:callout.text>
        </flux:callout>

        {{-- TAB KAMERA / MANUAL --}}
        <div class="inline-flex w-full rounded-xl bg-zinc-100 p-1 shadow-sm dark:bg-zinc-800">
            <button x-on:click="tab = 'kamera'"
                x-bind:class="tab === 'kamera' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="w-full cursor-pointer rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200">
                <flux:icon name="camera" class="me-1 inline size-4" /> Kamera
            </button>
            <button x-on:click="tab = 'manual'"
                x-bind:class="tab === 'manual' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="w-full cursor-pointer rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200">
                <flux:icon name="pencil" class="me-1 inline size-4" /> Ketik Kode / SN
            </button>
        </div>

        {{-- VIEWFINDER (placeholder kamera) --}}
        <div x-show="tab === 'kamera'" x-cloak class="space-y-3">
            <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-zinc-900">
                <div class="absolute inset-0 flex items-center justify-center">
                    <flux:icon name="qr-code" class="size-20 text-zinc-700" />
                </div>
                <div class="absolute inset-12">
                    <div class="absolute left-0 top-0 size-10 rounded-tl-xl border-l-4 border-t-4 border-red-500"></div>
                    <div class="absolute right-0 top-0 size-10 rounded-tr-xl border-r-4 border-t-4 border-red-500"></div>
                    <div class="absolute bottom-0 left-0 size-10 rounded-bl-xl border-b-4 border-l-4 border-red-500"></div>
                    <div class="absolute bottom-0 right-0 size-10 rounded-br-xl border-b-4 border-r-4 border-red-500"></div>
                </div>
                <div class="absolute inset-x-0 bottom-4 text-center">
                    <span class="rounded-full bg-black/60 px-3 py-1.5 text-xs text-white">Arahkan kamera ke QR label unit</span>
                </div>
            </div>
            <flux:text size="xs" class="text-center">Kamera aktif saat integrasi scanner dipasang. Gunakan tab "Ketik Kode / SN".</flux:text>
        </div>

        {{-- INPUT MANUAL (fungsional) --}}
        <div x-show="tab === 'manual'" class="space-y-3">
            <form wire:submit="lookup" class="flex gap-2">
                <flux:input wire:model="query" icon="qr-code" placeholder="mis. INV-0007-005 atau ZTE10G-88342" class="flex-1" />
                <flux:button type="submit" variant="primary" icon="magnifying-glass" class="cursor-pointer">Cari</flux:button>
            </form>
            <flux:text size="xs">Coba: <span class="font-mono">ZTE10G-88342</span> (lengkap) · <span class="font-mono">INV-0007-005</span> (kosong).</flux:text>
            <flux:checkbox wire:model.live="simulateOutsider" label="Simulasikan: saya bukan anggota tim project ini" />
        </div>

        {{-- HASIL --}}
        @if ($searched && $found === null)
            <div class="rounded-2xl border border-amber-200 bg-white p-5 shadow-lg dark:border-amber-900 dark:bg-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-5 text-amber-500" />
                    <flux:text size="sm">Kode / SN "<span class="font-mono">{{ $query }}</span>" tidak ditemukan di sistem.</flux:text>
                </div>
            </div>
        @elseif ($found !== null)
            @if ($found['sn'] !== null)
                {{-- STATE 1: UNIT SUDAH LENGKAP → detail read-only untuk siapa pun --}}
                <div class="rounded-2xl border border-green-200 bg-white p-5 shadow-lg dark:border-green-900 dark:bg-zinc-800">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center gap-2">
                                <flux:icon name="check-circle" class="size-5 text-green-500" />
                                <flux:heading class="font-mono">{{ $found['sn'] }}</flux:heading>
                            </div>
                            <flux:text size="sm">{{ $found['item'] }} · {{ $found['spektek_name'] }}</flux:text>
                            <div class="flex items-center gap-2 pt-1">
                                <flux:badge size="sm" :color="$this->statusColor($found['status'])">{{ $this->statusLabel($found['status']) }}</flux:badge>
                                <flux:text size="xs">{{ $found['destination'] ?? 'Tujuan belum diatur' }}</flux:text>
                            </div>
                        </div>
                        <a href="{{ route('inventaris.unit-detail', $found['id']) }}" wire:navigate class="shrink-0">
                            <flux:button size="sm" variant="primary" icon:trailing="arrow-right">Buka Detail</flux:button>
                        </a>
                    </div>
                </div>
            @elseif ($simulateOutsider)
                {{-- STATE 3: BELUM LENGKAP + BUKAN TIM → read-only terkunci --}}
                <div class="space-y-3 rounded-2xl border border-amber-200 bg-white p-5 shadow-lg dark:border-amber-900 dark:bg-zinc-800">
                    <div class="flex items-center gap-2">
                        <flux:icon name="lock-closed" class="size-5 text-amber-500" />
                        <div>
                            <flux:heading size="sm" class="font-mono">{{ $found['code'] }}</flux:heading>
                            <flux:text size="xs">{{ $found['item'] }} · Batch {{ $found['receipt_code'] }}</flux:text>
                        </div>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30">
                        <flux:text size="sm">Unit ini belum memiliki SN. Anda bukan anggota tim <strong>{{ $found['spektek_name'] }}</strong>, jadi tidak dapat mengisinya — hanya melihat status.</flux:text>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div>
                            <flux:text size="sm" class="font-medium">Hubungi tim project</flux:text>
                            <flux:text size="xs">{{ $found['receipt_by'] }} (PIC batch)</flux:text>
                        </div>
                        <flux:button size="sm" variant="outline" icon="chat-bubble-left-right" class="cursor-pointer">Hubungi</flux:button>
                    </div>
                </div>
            @else
                {{-- STATE 2: BELUM LENGKAP + ANGGOTA TIM → form pengisian (fungsional) --}}
                <div class="space-y-4 rounded-2xl border border-blue-200 bg-white p-5 shadow-lg dark:border-blue-900 dark:bg-zinc-800">
                    <div class="flex items-center gap-2">
                        <flux:icon name="pencil-square" class="size-5 text-blue-500" />
                        <div>
                            <flux:heading size="sm" class="font-mono">{{ $found['code'] }}</flux:heading>
                            <flux:text size="xs">{{ $found['item'] }} · Batch {{ $found['receipt_code'] }} — belum ada SN. Silakan lengkapi.</flux:text>
                        </div>
                    </div>

                    <flux:field>
                        <flux:label>Serial number</flux:label>
                        <flux:input wire:model="assignSn" icon="qr-code" placeholder="Scan atau ketik SN dari fisik barang..." />
                    </flux:field>

                    <flux:field>
                        <flux:label>Tujuan / lokasi (opsional)</flux:label>
                        <flux:input wire:model="assignDestination" placeholder="Contoh: POP Jababeka" />
                    </flux:field>

                    <div>
                        <flux:label>Foto fisik + SN</flux:label>
                        <button type="button" class="mt-1.5 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 py-4 text-sm text-zinc-400 transition hover:border-blue-400 hover:text-blue-500 dark:border-zinc-600 dark:text-zinc-500">
                            <flux:icon name="camera" class="size-5" /> Ambil / unggah foto
                        </button>
                    </div>

                    <flux:button variant="primary" icon="check" class="w-full cursor-pointer" wire:click="complete" wire:loading.attr="disabled">
                        Simpan &amp; Lengkapi Unit
                    </flux:button>
                </div>
            @endif
        @endif

    </div>
</div>
