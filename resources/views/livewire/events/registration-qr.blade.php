<?php

use Livewire\Volt\Component;

new class extends Component {
    public string $step = 'identify'; // identify | reimburse | success
    public string $nip = '';

    public array $event = [
        'title' => 'Annual Design Conference 2026',
        'date' => '22 - 25 Apr 2026',
        'location' => 'Hotel Mulia, Jakarta',
        'wa_group' => 'https://chat.whatsapp.com/dummy-group-link',
    ];

    public array $participant = [
        'id' => 1,
        'name' => 'John Doe',
        'jabatan' => 'Muda Wira III/b',
        'nip' => '198501012010011001',
        'phone' => '+62 812-3456-7890',
        'organization' => 'Kejaksaan Negeri Pekanbaru',
        'qr_token' => 'EVT1-PRT1-X9K2L7M',
    ];

    public array $reimburse = [
        'transport' => 850000,
        'accommodation' => 1500000,
        'meal' => 300000,
    ];

    public array $guests = [];

    public function mount(): void
    {
        $names = [
            ['John Doe', 'Kejaksaan Negeri Pekanbaru', '09:12'],
            ['Jane Smith', 'Kejati DKI Jakarta', '09:18'],
            ['Alice Johnson', 'Kejaksaan Negeri NTB', '09:24'],
            ['Budi Santoso', 'Kejati Jawa Barat', '09:31'],
            ['Siti Aminah', 'Kejaksaan Negeri Bandung', '09:45'],
            ['Rahmat Hidayat', 'Kejati Sumatera Utara', '09:52'],
        ];

        foreach ($names as $i => [$name, $org, $time]) {
            $this->guests[] = [
                'id' => $i + 1,
                'name' => $name,
                'jabatan' => 'Muda Wira III/b',
                'nip' => '19850' . random_int(100000, 999999),
                'organization' => $org,
                'registered_at' => $time,
            ];
        }
    }

    public function next(): void
    {
        $this->step = match ($this->step) {
            'identify' => 'reimburse',
            'reimburse' => 'success',
            default => 'identify',
        };
    }

    public function reset_flow(): void
    {
        $this->step = 'identify';
        $this->nip = '';
    }
}; ?>

<div>
    <div class="w-full space-y-6">
        {{-- Event Banner --}}
        <div class="bg-white rounded-2xl border border-zinc-200 p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wider text-accent font-semibold mb-1">Day-1 Registration</p>
                <h2 class="text-lg font-bold text-zinc-900">{{ $event['title'] }}</h2>
                <p class="text-xs text-zinc-500 mt-1">
                    <flux:icon.calendar class="w-3.5 h-3.5 inline -mt-0.5" /> {{ $event['date'] }}
                    <span class="mx-2">·</span>
                    <flux:icon.map-pin class="w-3.5 h-3.5 inline -mt-0.5" /> {{ $event['location'] }}
                </p>
            </div>

            {{-- Stepper --}}
            <div class="flex items-center gap-2 text-xs">
                @foreach (['identify' => 'Identify', 'reimburse' => 'Reimburse', 'success' => 'Done'] as $key => $label)
                    @php $active = $step === $key; $idx = array_search($key, ['identify', 'reimburse', 'success']); @endphp
                    <div class="flex items-center gap-2">
                        <div @class([
                            'w-7 h-7 rounded-full flex items-center justify-center font-semibold',
                            'bg-accent text-white' => $active,
                            'bg-zinc-100 text-zinc-500' => !$active,
                        ])>{{ $idx + 1 }}</div>
                        <span @class(['font-medium' => $active, 'text-zinc-500' => !$active])>{{ $label }}</span>
                        @if (!$loop->last)
                            <flux:icon.chevron-right class="w-3 h-3 text-zinc-300" />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            {{-- Main Wizard --}}
            <div class="lg:col-span-3 bg-white p-6 lg:p-8 rounded-2xl shadow-sm border border-zinc-200">
                {{-- STEP 1: IDENTIFY --}}
                @if ($step === 'identify')
                    <div class="space-y-5">
                        <div>
                            <h1 class="text-2xl font-bold text-zinc-800">Masukkan NIP Anda</h1>
                            <p class="text-zinc-500 text-sm mt-1">Scan barcode kartu pegawai atau ketik NIP secara manual.</p>
                        </div>

                        <div class="bg-zinc-50 rounded-xl p-6 flex flex-col items-center">
                            <flux:icon.identification class="w-16 h-16 text-accent mb-3" />
                            <flux:input wire:model="nip" placeholder="Contoh: 198501012010011001"
                                class="w-full max-w-md text-center! text-lg!" autofocus />
                        </div>

                        <flux:button wire:click="next" variant="primary"  icon-trailing="arrow-right"
                            class="w-full">Lanjutkan</flux:button>
                    </div>
                @endif

                {{-- STEP 2: REIMBURSE --}}
                @if ($step === 'reimburse')
                    <div class="space-y-5">
                        <div>
                            <h1 class="text-2xl font-bold text-zinc-800">Form Reimbursement</h1>
                            <p class="text-zinc-500 text-sm mt-1">Lengkapi rincian biaya perjalanan untuk reimbursement.</p>
                        </div>

                        <div class="rounded-xl bg-blue-50 border border-blue-100 p-4 flex items-center gap-3">
                            <img src="https://i.pravatar.cc/100?img=12" class="w-10 h-10 rounded-full" />
                            <div class="text-sm">
                                <p class="font-semibold text-zinc-900">{{ $participant['name'] }}</p>
                                <p class="text-xs text-zinc-600">{{ $participant['jabatan'] }} · NIP {{ $participant['nip'] }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Transport</flux:label>
                                <flux:input wire:model="reimburse.transport" type="number" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Akomodasi</flux:label>
                                <flux:input wire:model="reimburse.accommodation" type="number" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Konsumsi</flux:label>
                                <flux:input wire:model="reimburse.meal" type="number" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Upload Bukti</flux:label>
                                <flux:input type="file" />
                            </flux:field>
                        </div>

                        <div class="rounded-xl bg-zinc-50 p-4 flex justify-between items-center">
                            <span class="text-sm text-zinc-600">Total Reimbursement</span>
                            <span class="text-xl font-bold text-accent">
                                Rp {{ number_format(array_sum($reimburse), 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="flex gap-3">
                            <flux:button wire:click="reset_flow" variant="outline"  class="flex-1">Kembali</flux:button>
                            <flux:button wire:click="next" variant="primary"  icon-trailing="arrow-right"
                                class="flex-1">Generate QR</flux:button>
                        </div>
                    </div>
                @endif

                {{-- STEP 3: SUCCESS --}}
                @if ($step === 'success')
                    <div class="space-y-5 text-center">
                        <div class="flex justify-center">
                            <div class="w-16 h-16 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                <flux:icon.check class="w-9 h-9" />
                            </div>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-zinc-800">Registrasi Berhasil!</h1>
                            <p class="text-zinc-500 text-sm mt-1">Simpan QR Code di bawah untuk check-in di hari berikutnya.</p>
                        </div>

                        <div class="flex justify-center" wire:ignore>
                            <div class="bg-white p-4 rounded-2xl border-2 border-dashed border-zinc-300">
                                <div id="qrcode-success"></div>
                                <p class="mt-3 font-mono text-xs text-zinc-500">{{ $participant['qr_token'] }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl bg-zinc-50 p-4 text-left text-sm space-y-1">
                            <p><span class="text-zinc-500">Nama:</span> <span class="font-medium">{{ $participant['name'] }}</span></p>
                            <p><span class="text-zinc-500">Organisasi:</span> <span class="font-medium">{{ $participant['organization'] }}</span></p>
                            <p><span class="text-zinc-500">Event:</span> <span class="font-medium">{{ $event['title'] }}</span></p>
                        </div>

                        <div class="flex gap-3">
                            <flux:button href="{{ $event['wa_group'] }}" target="_blank" variant="primary"
                                icon="chat-bubble-left-right" class="flex-1">Join Group WhatsApp</flux:button>
                            <flux:button wire:click="reset_flow" variant="outline"  icon="arrow-path">
                                Peserta Berikutnya
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Recent Registrations --}}
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-zinc-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-zinc-800">Recent Registrations</h2>
                        <p class="text-xs text-zinc-500">{{ count($guests) }} peserta hari ini</p>
                    </div>
                    <flux:badge color="green" size="sm">
                        <span class="flex h-1.5 w-1.5 rounded-full bg-green-600 mr-1.5 animate-pulse"></span>
                        Live
                    </flux:badge>
                </div>

                <div class="max-h-120 overflow-auto space-y-2">
                    @foreach ($guests as $g)
                        <div wire:key="reg-{{ $g['id'] }}"
                            class="flex items-center gap-3 p-3 rounded-xl hover:bg-zinc-50 transition border border-transparent hover:border-zinc-200">
                            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-semibold">
                                {{ Str::substr($g['name'], 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 truncate">{{ $g['name'] }}</p>
                                <p class="text-[11px] text-zinc-500 truncate">{{ $g['organization'] }}</p>
                            </div>
                            <span class="text-[11px] text-zinc-400 font-mono">{{ $g['registered_at'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        function renderQR() {
            const el = document.getElementById('qrcode-success');
            if (!el || el.dataset.rendered) return;
            el.innerHTML = '';
            new QRCode(el, {
                text: @js($participant['qr_token']),
                width: 220,
                height: 220,
                colorDark: '#18181b',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            });
            el.dataset.rendered = '1';
        }

        document.addEventListener('livewire:navigated', renderQR);
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', renderQR);
            renderQR();
        });
    </script>
@endpush
