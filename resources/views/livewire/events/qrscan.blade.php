<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $event = [
        'title' => 'Annual Design Conference 2026',
        'session' => 'Day 2 — Keynote: Designing for Public Trust',
        'time' => '09:00 - 10:30 WIB',
        'location' => 'Ballroom A · Hotel Mulia',
    ];

    public array $stats = [
        'checked_in' => 86,
        'total' => 124,
        'last_minute' => 12,
    ];

    public array $guests = [];

    public function mount(): void
    {
        $names = [
            ['John Doe', 'Kejaksaan Negeri Pekanbaru'],
            ['Jane Smith', 'Kejati DKI Jakarta'],
            ['Alice Johnson', 'Kejaksaan Negeri Nusa Tenggara Barat'],
            ['Budi Santoso', 'Kejati Jawa Barat'],
            ['Siti Aminah', 'Kejaksaan Negeri Bandung'],
            ['Rahmat Hidayat', 'Kejati Sumatera Utara'],
            ['Dewi Lestari', 'Kejaksaan Negeri Surabaya'],
        ];

        foreach ($names as $i => [$name, $org]) {
            $this->guests[] = [
                'id' => $i + 1,
                'name' => $name,
                'jabatan' => 'Muda Wira III/b',
                'nip' => '19850' . random_int(100000, 999999),
                'phone' => '+62 812-' . random_int(1000, 9999) . '-' . random_int(1000, 9999),
                'organization' => $org,
                'check_in_time' => now()->subMinutes(random_int(1, 90)),
            ];
        }
    }
}; ?>

<div>
    <div class="w-full space-y-6">
        {{-- Session Banner --}}
        <div class="bg-white rounded-2xl border border-zinc-200 p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wider text-accent font-semibold mb-1">Active Session</p>
                <h2 class="text-lg font-bold text-zinc-900">{{ $event['session'] }}</h2>
                <p class="text-xs text-zinc-500 mt-1">
                    <flux:icon.clock class="w-3.5 h-3.5 inline -mt-0.5" /> {{ $event['time'] }}
                    <span class="mx-2">·</span>
                    <flux:icon.map-pin class="w-3.5 h-3.5 inline -mt-0.5" /> {{ $event['location'] }}
                </p>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $stats['checked_in'] }}</p>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500">Checked-in</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-zinc-700">{{ $stats['total'] }}</p>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500">Total</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-amber-600">{{ $stats['last_minute'] }}</p>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500">Last 15 min</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            {{-- Scanner --}}
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-zinc-200 flex flex-col items-center">
                <div class="w-full text-center mb-4">
                    <h1 class="text-xl font-bold text-zinc-800 mb-1">Scan QR Peserta</h1>
                    <p class="text-zinc-500 text-sm">Arahkan QR code peserta ke dalam kotak pemindai.</p>
                </div>

                <div id="qr-reader-container" wire:ignore class="w-full">
                    <div id="qr-reader" style="transform: scaleX(-1);"
                        class="w-full rounded-xl overflow-hidden border-2 border-dashed border-zinc-300 bg-zinc-900 aspect-square">
                    </div>
                </div>

                <div class="mt-5 w-full">
                    <p class="text-xs text-zinc-500 mb-2">Atau input manual NIP / Kode</p>
                    <div class="flex gap-2">
                        <flux:input placeholder="Masukkan NIP atau scan barcode..." class="flex-1!" autofocus />
                        <flux:button variant="primary" icon="check">Check-in</flux:button>
                    </div>
                </div>
            </div>

            {{-- Recent Check-ins --}}
            <div class="lg:col-span-3 bg-white p-6 rounded-2xl shadow-sm border border-zinc-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-zinc-800">Recent Check-ins</h2>
                        <p class="text-xs text-zinc-500">{{ $event['title'] }}</p>
                    </div>
                    <flux:badge color="green" size="sm">
                        <span class="flex h-1.5 w-1.5 rounded-full bg-green-600 mr-1.5 animate-pulse"></span>
                        Live
                    </flux:badge>
                </div>

                <div class="max-h-120 overflow-auto rounded-xl border border-zinc-100">
                    <table class="w-full text-sm text-left">
                        <thead class="sticky top-0 text-xs text-zinc-600 uppercase bg-zinc-50 z-10 tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Jabatan</th>
                                <th class="px-4 py-3">Organisasi</th>
                                <th class="px-4 py-3 text-right">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($guests as $data)
                                <tr wire:key="scan-{{ $data['id'] }}" class="hover:bg-zinc-50 transition">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs font-semibold">
                                                {{ Str::substr($data['name'], 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-zinc-900">{{ $data['name'] }}</p>
                                                <p class="text-[11px] text-zinc-500">NIP {{ $data['nip'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 text-xs">{{ $data['jabatan'] }}</td>
                                    <td class="px-4 py-3 text-zinc-600 text-xs">{{ $data['organization'] }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center gap-1 text-xs text-green-700 font-medium">
                                            <flux:icon.check-circle class="w-3.5 h-3.5" />
                                            {{ $data['check_in_time']->format('H:i:s') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-zinc-400">
                                        <flux:icon.qr-code class="w-10 h-10 mx-auto mb-2 opacity-40" />
                                        <p>Belum ada check-in. Mulai pindai QR.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        #qr-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            background: black;
        }

        #qr-reader div[style*="background: rgba(9, 9, 9"] {
            display: none !important;
        }
    </style>
</div>

@push('scripts')
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        let html5QrCode;
        let isScannerRunning = false;
        let lastScannedCode = "";
        let lastScannedTime = 0;
        let isProcessing = false;

        function startScanner() {
            const qrElement = document.getElementById("qr-reader");
            if (!qrElement || isScannerRunning) return;

            html5QrCode = new Html5Qrcode("qr-reader");

            Html5Qrcode.getCameras().then((cameras) => {
                if (!cameras.length) return console.error("Tidak ada kamera terdeteksi.");

                html5QrCode.start(
                    cameras[0].id, {
                        fps: 10,
                        qrbox: (vw, vh) => {
                            const size = Math.max(Math.min(vw, vh) * 0.7, 150);
                            return { width: size, height: size };
                        }
                    },
                    (decodedText) => {
                        if (isProcessing) return;
                        const now = Date.now();
                        if (decodedText === lastScannedCode && now - lastScannedTime < 3000) return;

                        isProcessing = true;
                        lastScannedCode = decodedText;
                        lastScannedTime = now;
                        html5QrCode.pause(true);
                        if (window.Livewire) Livewire.dispatch("checkin", { code: decodedText });
                    },
                    () => {}
                ).then(() => { isScannerRunning = true; })
                .catch((err) => console.error("Gagal memulai kamera:", err));
            }).catch((err) => console.error("Akses kamera ditolak:", err));
        }

        document.addEventListener("livewire:navigated", () => setTimeout(startScanner, 300));

        function setQrBoxColor(color) {
            const corners = document.querySelectorAll("#qr-shaded-region div");
            corners.forEach(c => c.style.backgroundColor = color || "rgb(255, 255, 255)");
        }

        document.addEventListener('livewire:initialized', () => {
            const flash = (color) => {
                const region = document.querySelector("#qr-reader > video")?.parentElement;
                if (region) {
                    region.style.border = `3px solid ${color}`;
                    setTimeout(() => { region.style.border = ""; }, 1000);
                }
                setQrBoxColor(color);
                setTimeout(() => {
                    if (html5QrCode && isScannerRunning) html5QrCode.resume();
                    setQrBoxColor("");
                    isProcessing = false;
                }, 1000);
            };

            Livewire.on('qr-error', () => flash("#ef4444"));
            Livewire.on('qr-success', () => flash("#22c55e"));
        });
    </script>
@endpush
