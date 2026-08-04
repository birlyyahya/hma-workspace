<?php

use App\Services\CaCache;
use App\Services\CaKategoriPemasukanKegiatan;
use App\Services\CaKategoriPengeluaran;
use App\Services\CaWriter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Catat Transaksi Cepat'])]
class extends Component {
    use WithFileUploads;

    /** Alur langkah: jenis | dompet | form | success */
    public string $step = 'jenis';

    /** Jenis dompet terpilih: 'pl' | 'kegiatan' */
    public ?string $walletType = null;

    public string $kodeCa = '';

    public string $walletLabel = '';

    /** Arah transaksi (hanya relevan untuk dompet kegiatan). */
    public string $arah = 'pengeluaran';

    public $jumlah;

    public ?string $kategori = null;

    public string $deskripsi = '';

    public string $tanggal = '';

    public $bukti;

    /** @var array<string, mixed> Ringkasan transaksi terakhir untuk layar sukses. */
    public array $lastSaved = [];

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
    }

    /**
     * Dompet CA PL aktif milik user (maksimal satu periode berjalan).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function caPlWallet(): array
    {
        return collect(app(CaCache::class)->DompetCaActiveTransaksi(Auth::id()))
            ->firstWhere('is_active', 1) ?? [];
    }

    /**
     * Dompet kegiatan aktif yang boleh dicatat user (pemilik/kolaborator).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function kegiatanWallets(): array
    {
        $data = app(CaCache::class)->listWalletKegiatan(Auth::id())['data'] ?? [];

        return collect($data)->where('is_active', 1)->values()->all();
    }

    public function pilihPl(): void
    {
        $wallet = $this->caPlWallet();

        if (empty($wallet['kode_ca'])) {
            Toaster::error('Belum ada dompet CA PL aktif.');

            return;
        }

        $this->walletType = 'pl';
        $this->kodeCa = $wallet['kode_ca'];
        $this->walletLabel = 'CA PL · '.$wallet['kode_ca'];
        $this->arah = 'pengeluaran';
        $this->resetFormFields();
        $this->step = 'form';
    }

    public function pilihKegiatan(): void
    {
        if (empty($this->kegiatanWallets())) {
            Toaster::error('Belum ada dompet kegiatan aktif.');

            return;
        }

        $this->walletType = 'kegiatan';
        $this->step = 'dompet';
    }

    public function pilihDompet(string $kode, string $judul): void
    {
        $this->kodeCa = $kode;
        $this->walletLabel = $judul;
        $this->arah = 'pengeluaran';
        $this->resetFormFields();
        $this->step = 'form';
    }

    /**
     * Daftar kategori mengikuti jenis dompet & arah: kategori sumber dana untuk
     * pemasukan kegiatan, kategori belanja untuk selain itu (CA PL selalu keluar).
     *
     * @return array<int, string>
     */
    public function kategoriOptions(): array
    {
        if ($this->walletType === 'kegiatan' && $this->arah === 'penerimaan') {
            return CaKategoriPemasukanKegiatan::labels();
        }

        return CaKategoriPengeluaran::labels();
    }

    /**
     * Ikon Flux untuk chip kategori (pemasukan dipetakan manual, selain itu
     * ambil dari enum pengeluaran).
     */
    public function kategoriIcon(string $label): string
    {
        return match ($label) {
            'Dana Awal' => 'banknotes',
            'Top Up Tambahan' => 'plus-circle',
            'Pengembalian' => 'arrow-uturn-left',
            default => CaKategoriPengeluaran::iconForLabel($label),
        };
    }

    /**
     * Kategori terpilih bisa jadi tak berlaku lagi begitu arah diganti.
     */
    public function updatedArah(): void
    {
        if (! in_array($this->kategori, $this->kategoriOptions(), true)) {
            $this->kategori = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'jumlah' => ['required', 'integer', 'min:1'],
            'kategori' => ['required', 'string', Rule::in($this->kategoriOptions())],
            'deskripsi' => ['nullable', 'string'],
            'tanggal' => ['required', 'date'],
            'bukti' => ['required', 'file', 'mimes:jpg,png,pdf', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'jumlah.required' => 'Isi nominal transaksi terlebih dahulu.',
            'jumlah.min' => 'Nominal minimal Rp 1.',
            'kategori.required' => 'Pilih kategori terlebih dahulu.',
            'kategori.in' => 'Kategori tidak sesuai dengan arah transaksi.',
            'bukti.required' => 'Unggah atau foto bukti terlebih dahulu.',
            'bukti.file' => 'Berkas bukti tidak valid.',
            'bukti.mimes' => 'Bukti harus berupa JPG, PNG, atau PDF.',
            'bukti.max' => 'Ukuran bukti maksimal 2 MB.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $jenis = $this->walletType === 'kegiatan' ? $this->arah : 'pengeluaran';

        $payload = [
            'jumlah' => (int) $this->jumlah,
            'kategori' => $this->kategori,
            'deskripsi' => $this->deskripsi,
            'tanggal' => $this->tanggal,
            'jenis' => $jenis,
        ];

        if ($this->bukti) {
            $payload['bukti'] = [
                'contents' => $this->bukti->get(),
                'name' => $this->bukti->getClientOriginalName(),
            ];
        }

        if ($this->walletType === 'pl') {
            $payload['user_id'] = Auth::id();
            $result = app(CaWriter::class)->createTransactionCaPl($this->kodeCa, $payload);
        } else {
            $result = app(CaWriter::class)->createTransaksiKegiatan($this->kodeCa, $payload);
        }

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menyimpan transaksi.'));

            return;
        }

        $this->lastSaved = [
            'wallet' => $this->walletLabel,
            'jenis' => $jenis,
            'jumlah' => (int) $this->jumlah,
            'kategori' => $this->kategori,
            'tanggal' => $this->tanggal,
        ];

        Toaster::success('Transaksi berhasil dicatat.');
        $this->step = 'success';
    }

    public function resetFormFields(): void
    {
        $this->reset(['jumlah', 'kategori', 'deskripsi', 'bukti']);
        $this->tanggal = now()->format('Y-m-d');
        $this->resetValidation();
    }

    /**
     * Catat lagi: kembali ke pemilihan jenis dompet dengan form bersih.
     */
    public function catatLagi(): void
    {
        $this->reset(['walletType', 'kodeCa', 'walletLabel', 'lastSaved']);
        $this->arah = 'pengeluaran';
        $this->resetFormFields();
        $this->step = 'jenis';
    }

    /**
     * Tombol "Ganti" di form: balik ke pemilih dompet (kegiatan) atau jenis (PL).
     */
    public function ganti(): void
    {
        $this->step = $this->walletType === 'kegiatan' ? 'dompet' : 'jenis';
    }

    /**
     * Tombol kembali di top bar: mundur satu langkah, atau ke dashboard.
     */
    public function kembali(): void
    {
        if ($this->step === 'form') {
            $this->ganti();
        } elseif ($this->step === 'dompet') {
            $this->walletType = null;
            $this->step = 'jenis';
        }
    }

    /**
     * Palet warna "kantong" per indeks, konsisten dengan kartu kegiatan lain.
     *
     * @return array{dot: string, tint: string, text: string}
     */
    public function pocketTone(int $index): array
    {
        $palette = [
            ['dot' => 'bg-ca-pocket-1', 'tint' => 'bg-ca-pocket-1/10 dark:bg-ca-pocket-1/15', 'text' => 'text-ca-pocket-1'],
            ['dot' => 'bg-ca-pocket-2', 'tint' => 'bg-ca-pocket-2/10 dark:bg-ca-pocket-2/15', 'text' => 'text-ca-pocket-2'],
            ['dot' => 'bg-ca-pocket-3', 'tint' => 'bg-ca-pocket-3/10 dark:bg-ca-pocket-3/15', 'text' => 'text-ca-pocket-3'],
            ['dot' => 'bg-ca-pocket-4', 'tint' => 'bg-ca-pocket-4/10 dark:bg-ca-pocket-4/15', 'text' => 'text-ca-pocket-4'],
            ['dot' => 'bg-ca-pocket-5', 'tint' => 'bg-ca-pocket-5/10 dark:bg-ca-pocket-5/15', 'text' => 'text-ca-pocket-5'],
        ];

        return $palette[$index % count($palette)];
    }
}; ?>

<div>
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
        {{-- ================= TOP BAR ================= --}}
        <div class="sticky top-0 z-20 border-b border-zinc-200 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90">
            <div class="mx-auto flex max-w-md items-center gap-3 px-4 py-3">
                @if ($step === 'jenis')
                <a href="{{ route('dashboard') }}" wire:navigate
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                    <flux:icon name="chevron-left" class="size-5 text-zinc-700 dark:text-zinc-200" />
                </a>
                @elseif ($step !== 'success')
                <button type="button" wire:click="kembali"
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                    <flux:icon name="chevron-left" class="size-5 text-zinc-700 dark:text-zinc-200" />
                </button>
                @else
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-ca-copper/10 text-ca-copper-deep dark:bg-ca-copper/15 dark:text-ca-copper">
                    <flux:icon name="check" class="size-5" />
                </span>
                @endif

                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-ca-copper">Catat Cepat</p>
                    <h1 class="truncate text-base font-semibold leading-tight text-zinc-900 dark:text-white">
                        @switch($step)
                            @case('jenis') Pilih Dompet @break
                            @case('dompet') Dompet Kegiatan @break
                            @case('form') Catat Transaksi @break
                            @default Transaksi Tersimpan
                        @endswitch
                    </h1>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-md px-4 py-5 pb-28">

            {{-- ================= STEP 1: PILIH JENIS DOMPET ================= --}}
            @if ($step === 'jenis')
            <div class="space-y-4">
                <p class="px-1 text-sm text-zinc-500 dark:text-zinc-400">Mau catat transaksi di dompet mana?</p>

                {{-- CA PL: kartu "dompet fisik" gelap, mencerminkan kartu utama di ca-index --}}
                @php $pl = $this->caPlWallet(); @endphp
                <button type="button" wire:click="pilihPl" @disabled(empty($pl['kode_ca']))
                    @class([
                        'group relative w-full overflow-hidden rounded-3xl p-5 text-left text-white shadow-lg shadow-black/10 transition',
                        'bg-linear-to-br from-ca-ink-lift to-ca-ink active:scale-[0.98]' => ! empty($pl['kode_ca']),
                        'cursor-not-allowed bg-zinc-300 dark:bg-zinc-800' => empty($pl['kode_ca']),
                    ])>
                    <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: repeating-linear-gradient(115deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 22px);"></div>
                    <div class="pointer-events-none absolute -right-8 -top-8 size-32 rounded-full bg-ca-gold/20 blur-2xl"></div>
                    <div class="relative flex items-center gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                            <flux:icon name="wallet" class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-gold">Cash Advance PL</p>
                            @if (! empty($pl['kode_ca']))
                            <p class="mt-0.5 truncate text-sm text-white/60">{{ $pl['kode_ca'] }}</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums">Rp {{ number_format($pl['saldo_akhir'] ?? 0, 0, ',', '.') }}</p>
                            @else
                            <p class="mt-1 text-sm text-zinc-100/80">Belum ada dompet CA PL aktif</p>
                            @endif
                        </div>
                        @if (! empty($pl['kode_ca']))
                        <flux:icon name="chevron-right" class="size-5 shrink-0 text-white/60 transition group-hover:translate-x-0.5 group-hover:text-white" />
                        @endif
                    </div>
                </button>

                {{-- Dompet Kegiatan: kartu copper hangat --}}
                @php $kegiatan = $this->kegiatanWallets(); @endphp
                <button type="button" wire:click="pilihKegiatan" @disabled(empty($kegiatan))
                    @class([
                        'group relative w-full overflow-hidden rounded-3xl p-5 text-left text-white shadow-lg shadow-ca-copper/20 transition',
                        'bg-linear-to-br from-ca-copper to-ca-copper-deep active:scale-[0.98]' => ! empty($kegiatan),
                        'cursor-not-allowed bg-zinc-300 dark:bg-zinc-800' => empty($kegiatan),
                    ])>
                    <div class="pointer-events-none absolute -left-8 -bottom-10 size-36 rounded-full bg-white/15 blur-2xl"></div>
                    <div class="relative flex items-center gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/30 backdrop-blur">
                            <flux:icon name="briefcase" class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-white/80">Dompet Kegiatan</p>
                            @if (! empty($kegiatan))
                            <p class="mt-1 text-lg font-semibold leading-tight">{{ count($kegiatan) }} dompet aktif</p>
                            <p class="text-xs text-white/70">Pilih dompet lalu catat transaksi</p>
                            @else
                            <p class="mt-1 text-sm text-zinc-100/80">Belum ada dompet kegiatan aktif</p>
                            @endif
                        </div>
                        @if (! empty($kegiatan))
                        <flux:icon name="chevron-right" class="size-5 shrink-0 text-white/70 transition group-hover:translate-x-0.5 group-hover:text-white" />
                        @endif
                    </div>
                </button>
            </div>
            @endif

            {{-- ================= STEP 2: PILIH DOMPET KEGIATAN ================= --}}
            @if ($step === 'dompet')
            <div class="space-y-3">
                <p class="px-1 text-sm text-zinc-500 dark:text-zinc-400">Pilih dompet kegiatan yang mau dicatat.</p>
                @foreach ($this->kegiatanWallets() as $i => $item)
                @php $tone = $this->pocketTone($i); @endphp
                <button type="button" wire:key="quick-keg-{{ $item['kode_ca'] }}"
                    wire:click="pilihDompet('{{ $item['kode_ca'] }}', @js($item['judul_kegiatan']))"
                    class="group flex w-full items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 text-left transition active:scale-[0.99] hover:border-ca-copper/30 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-ca-copper/40">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full {{ $tone['tint'].' '.$tone['text'] }}">
                        <flux:icon name="briefcase" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $item['judul_kegiatan'] }}</p>
                        <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $item['kode_ca'] }}</p>
                        <p class="mt-1 text-sm font-bold tabular-nums {{ ($item['saldo_akhir'] ?? 0) < 0 ? 'text-red-600' : $tone['text'] }}">
                            Rp {{ number_format($item['saldo_akhir'] ?? 0, 0, ',', '.') }}
                        </p>
                    </div>
                    <flux:icon name="chevron-right" class="size-5 shrink-0 text-zinc-300 transition group-hover:translate-x-0.5 group-hover:text-ca-copper dark:text-zinc-600" />
                </button>
                @endforeach
            </div>
            @endif

            {{-- ================= STEP 3: FORM TRANSAKSI ================= --}}
            @if ($step === 'form')
            <div x-data="{ uploading: false }"
                x-on:livewire-upload-start="uploading = true"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-cancel="uploading = false">

                {{-- Konteks dompet terpilih + tombol ganti --}}
                <div class="mb-4 flex items-center gap-3 rounded-2xl border border-ca-copper/20 bg-ca-copper/5 p-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-ca-copper/15 text-ca-copper-deep dark:text-ca-copper">
                        <flux:icon name="{{ $walletType === 'pl' ? 'wallet' : 'briefcase' }}" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-400">Dompet</p>
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $walletLabel }}</p>
                    </div>
                    <button type="button" wire:click="ganti" class="shrink-0 text-xs font-semibold text-ca-copper hover:text-ca-copper-deep">Ganti</button>
                </div>

                <form wire:submit="save" class="space-y-4">

                    {{-- Arah (kegiatan saja) --}}
                    @if ($walletType === 'kegiatan')
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="mb-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Arah Transaksi</p>
                        <div class="grid grid-cols-2 gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                            <button type="button" wire:click="$set('arah', 'pengeluaran')"
                                @class([
                                    'flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition',
                                    'bg-white text-ca-copper-deep shadow-sm dark:bg-zinc-700 dark:text-ca-copper' => $arah === 'pengeluaran',
                                    'text-zinc-500 dark:text-zinc-400' => $arah !== 'pengeluaran',
                                ])>
                                <flux:icon name="arrow-up-right" class="size-4" /> Keluar
                            </button>
                            <button type="button" wire:click="$set('arah', 'penerimaan')"
                                @class([
                                    'flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition',
                                    'bg-white text-emerald-600 shadow-sm dark:bg-zinc-700 dark:text-emerald-400' => $arah === 'penerimaan',
                                    'text-zinc-500 dark:text-zinc-400' => $arah !== 'penerimaan',
                                ])>
                                <flux:icon name="arrow-down-left" class="size-4" /> Masuk
                            </button>
                        </div>
                    </div>
                    @endif

                    {{-- Jumlah --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="mb-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Nominal</p>
                        <x-rupiah-input model="jumlah" placeholder="0" />
                        @error('jumlah') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    {{-- Kategori chips --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="mb-3 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Kategori</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($this->kategoriOptions() as $opt)
                            @php $sel = $kategori === $opt; @endphp
                            <button type="button" wire:key="kat-{{ $opt }}" wire:click="$set('kategori', @js($opt))"
                                @class([
                                    'flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-xs font-medium transition',
                                    'border-ca-copper/40 bg-ca-copper/10 text-ca-copper-deep ring-2 ring-ca-copper/20 dark:text-ca-copper' => $sel,
                                    'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800' => ! $sel,
                                ])>
                                <flux:icon name="{{ $this->kategoriIcon($opt) }}" class="size-4 shrink-0" />
                                <span class="truncate">{{ $opt }}</span>
                            </button>
                            @endforeach
                        </div>
                        @error('kategori') <flux:error class="mt-2">{{ $message }}</flux:error> @enderror
                    </div>

                    {{-- Deskripsi --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="mb-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Deskripsi <span class="font-normal text-zinc-400">(opsional)</span></p>
                        <flux:textarea wire:model="deskripsi" rows="2" placeholder="Keterangan transaksi..." />
                    </div>

                    {{-- Tanggal --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="mb-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Tanggal</p>
                        <flux:input type="date" wire:model="tanggal" />
                        @error('tanggal') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    {{-- Bukti: pilih file + kamera langsung --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="mb-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Bukti <span class="font-normal text-zinc-400">(jpg, png, pdf &mdash; maks 2MB)</span></p>
                        {{-- Nama file native tidak bisa di-truncate; pakai span kustom (dir=rtl --}}
                        {{-- ellipsis di depan agar ekstensi tetap terlihat). --}}
                        <div class="flex w-full min-w-0 items-stretch gap-2">
                            <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                                <span class="shrink-0 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">Pilih File</span>
                                <span class="min-w-0 flex-1 truncate text-left text-zinc-600 dark:text-zinc-300" dir="rtl">
                                    {{ $bukti?->getClientOriginalName() ?? 'Belum ada file dipilih' }}
                                </span>
                                <input wire:model="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
                            </label>

                            {{-- Kamera langsung: input native (tanpa wire:model) + $wire.upload manual --}}
                            {{-- dengan callback agar loading tetap ke-drive walau event upload --}}
                            {{-- programatik terpancar di root komponen (bukan menetes ke sini). --}}
                            <label :class="uploading ? 'pointer-events-none opacity-60' : ''"
                                class="flex shrink-0 cursor-pointer items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 text-zinc-500 transition hover:border-ca-copper/40 hover:text-ca-copper dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400"
                                title="Ambil foto langsung">
                                <flux:icon name="camera" class="size-5" x-show="! uploading" />
                                <flux:icon name="arrow-path" class="size-5 animate-spin" x-show="uploading" x-cloak />
                                <input type="file" accept="image/*" capture="environment" class="sr-only"
                                    x-on:change="
                                        if (! $event.target.files.length) return;
                                        uploading = true;
                                        $wire.upload('bukti', $event.target.files[0],
                                            () => uploading = false,
                                            () => uploading = false,
                                            () => {},
                                            () => uploading = false,
                                        );
                                    " />
                            </label>
                        </div>
                        <span x-show="uploading" x-cloak class="mt-1 block animate-pulse text-xs text-zinc-500">Mengunggah bukti&hellip;</span>
                        @error('bukti') <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
                    </div>
                </form>

                {{-- Sticky submit --}}
                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
                    <div class="mx-auto max-w-md">
                        <flux:button wire:click="save" variant="primary" icon="check"
                            class="w-full bg-ca-copper! hover:bg-ca-copper-deep!"
                            x-bind:disabled="uploading" wire:loading.attr="disabled" wire:target="save">
                            <span x-show="! uploading" wire:loading.remove wire:target="save">Simpan Transaksi</span>
                            <span x-show="uploading" x-cloak class="animate-pulse">Menunggu unggahan&hellip;</span>
                            <span wire:loading wire:target="save" class="animate-pulse">Menyimpan&hellip;</span>
                        </flux:button>
                    </div>
                </div>
            </div>
            @endif

            {{-- ================= STEP 4: SUKSES ================= --}}
            @if ($step === 'success')
            @php $masuk = ($lastSaved['jenis'] ?? '') === 'penerimaan'; @endphp
            <div class="space-y-4">
                {{-- Hero --}}
                <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-ca-copper to-ca-copper-deep p-6 text-center text-white">
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute -right-10 -top-10 size-40 rounded-full bg-ca-gold/25 blur-2xl"></div>
                        <div class="absolute -left-10 -bottom-10 size-40 rounded-full bg-black/10 blur-2xl"></div>
                    </div>
                    <div class="relative">
                        <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-white/20 ring-2 ring-white/40">
                            <flux:icon name="check" class="size-8" />
                        </div>
                        <h2 class="mt-4 text-lg font-semibold">Transaksi Tercatat</h2>
                        <p class="mt-1 text-3xl font-bold tabular-nums">
                            {{ $masuk ? '+' : '−' }} Rp {{ number_format($lastSaved['jumlah'] ?? 0, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                {{-- Ringkasan --}}
                <div class="divide-y divide-zinc-100 rounded-2xl border border-zinc-200 bg-white p-4 dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-zinc-500">Dompet</span>
                        <span class="max-w-[60%] truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $lastSaved['wallet'] ?? '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-zinc-500">Arah</span>
                        <flux:badge size="sm" :color="$masuk ? 'green' : 'orange'">{{ $masuk ? 'Pemasukan' : 'Pengeluaran' }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-zinc-500">Kategori</span>
                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $lastSaved['kategori'] ?? '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-zinc-500">Tanggal</span>
                        <span class="text-sm font-medium tabular-nums text-zinc-900 dark:text-white">
                            {{ isset($lastSaved['tanggal']) ? \Carbon\Carbon::parse($lastSaved['tanggal'])->translatedFormat('d M Y') : '-' }}
                        </span>
                    </div>
                </div>

                {{-- Aksi --}}
                <div class="space-y-2">
                    <flux:button wire:click="catatLagi" variant="primary" icon="plus"
                        class="w-full bg-ca-copper! hover:bg-ca-copper-deep!">Catat Lagi</flux:button>
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <a href="{{ route('cashadvance') }}" wire:navigate class="block">
                            <flux:button variant="outline" icon="banknotes" class="w-full">Cash Advance</flux:button>
                        </a>
                        <a href="{{ route('dashboard') }}" wire:navigate class="block">
                            <flux:button variant="outline" icon="home" class="w-full">Dashboard</flux:button>
                        </a>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
