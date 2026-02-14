<?php

use Livewire\Volt\Component;
new
class extends Component {
    public $guests = [];

    public function mount() {
        $this->guests= [
             [
         'id' => 1,
        'name' => 'John Doe',
        'jabatan' => 'Muda Wira III/b',
        'nip' => '1234567890',
        'phone' => '+1 (555) 123-4567',
        'organization' => 'Kejaksaan Negeri Pekanbaru',
        'status' => 'checked_in',
        'confirm_attendance' => 'X123X',
        'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'event_id' => 1,
        'qr_generated' => null,
        'check_in_time' => now(),
       ],
         [
          'id' => 2,
          'name' => 'Jane Smith',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Acme Corp',
          'status' => 'registered',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
          'check_in_time' => now(),
         ],
         [
          'id' => 3,
          'name' => 'Alice Johnson',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Kejaksaan Negeri Nusa Tenggara Barat',
          'status' => 'cancelled',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
          'check_in_time' => now(),
         ],
         [
          'id' => 3,
          'name' => 'Alice Johnson',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Kejaksaan Negeri Nusa Tenggara Barat',
          'status' => 'cancelled',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
          'check_in_time' => now(),
         ],
         [
          'id' => 3,
          'name' => 'Alice Johnson',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Kejaksaan Negeri Nusa Tenggara Barat',
          'status' => 'cancelled',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
          'check_in_time' => now(),
         ],
         [
          'id' => 3,
          'name' => 'Alice Johnson',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Kejaksaan Negeri Nusa Tenggara Barat',
          'status' => 'cancelled',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
          'check_in_time' => now(),
         ],
         [
          'id' => 3,
          'name' => 'Alice Johnson',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Kejaksaan Negeri Nusa Tenggara Barat',
          'status' => 'cancelled',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
          'check_in_time' => now(),
         ],
    ];
    }
}; ?>

<div>
    <div class="w-full py-8 max-h-screen overflow-auto space-y-6">
        <div class="flex gap-8">
            <div class="flex flex-col items-center justify-center bg-white  p-5 lg:p-8 rounded-xl shadow-xl w-full h-full max-w-xl aspect-square">
                <h1 class="text-2xl font-bold text-zinc-800 mb-2 text-center">
                    Scan Your QR Code
                </h1>
                <p class="text-zinc-500  text-sm mb-4 text-center">
                    Turn on the switch to activate the scanner. Align the QR code inside the box.
                </p>

                <div id="qr-reader-container" wire:ignore>
                    <div id="qr-reader" style="transform: scaleX(-1);" class="w-full h-auto rounded-lg overflow-hidden border border-dashed border-zinc-400  mb-4">
                    </div>
                </div>

                <div class="mt-4 text-green-600 font-mono text-sm text-center">
                </div>
            </div>

            <div class="flex flex-col relative bg-white  p-5 lg:p-8 rounded-xl shadow-xl w-full">
                <h1 class="text-2xl font-bold text-zinc-800 mb-1">
                    Annual Design Conference
                </h1>
                <p class="text-zinc-500 text-sm mb-4 line-clamp-2">
                    Lorem ipsum dolor sit amet consectetur, adipisicing elit. Maiores, at? Lorem ipsum dolor, sit amet consectetur adipisicing elit. Voluptate rem ea magni, sunt optio, nesciunt culpa dolores error, excepturi adipisci velit rerum possimus accusamus beatae! Tenetur odio illum voluptatem reiciendis quibusdam aspernatur, qui praesentium nobis temporibus rerum, voluptates dolorum unde nisi ipsum repellat? Vero placeat ex odit fuga, laboriosam laborum!
                </p>
                <div class="max-h-[400px] overflow-auto aspect-square ">
                    <table class="w-full text-sm text-left text-gray-500 ">
                        <thead class="sticky top-0 text-xs text-gray-700 uppercase bg-gray-50 z-10  ">
                            <tr>
                                <th scope="col" class="px-6 py-3">Name</th>
                                <th scope="col" class="px-6 py-3">Jabatan</th>
                                <th scope="col" class="px-6 py-3">Organization</th>
                                <th scope="col" class="px-6 py-3">Phone</th>
                                <th scope="col" class="px-6 py-3">Check In</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->guests as $data)
                            <tr class="odd:bg-white  even:bg-gray-50  border-b  border-gray-200">
                                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                    <p>
                                        {{ $data['name'] }}
                                    </p>
                                    <p class="opacity-50 font-light">
                                        NIP {{ $data['nip'] }}
                                    </p>
                                </th>
                                <td class="px-6 py-4">
                                    {{ $data['jabatan']}}
                                </td>
                                <td class="px-6 py-4">
                                    {{ $data['organization']}}
                                </td>
                                <td class="px-6 py-4">
                                    {{ $data['phone'] }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ date_format(new DateTime($data['check_in_time']), 'H:i:s') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-zinc-500">
                                    No guests.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <style>
            #qr-reader video {
                width: 100% !important;
                height: auto !important;
                object-fit: cover !important;
                display: block !important;
                background: black;
            }

            #qr-reader div[style*="background: rgba(9, 9, 9"] {
                display: none !important;
            }

        </style>
    </div>

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
        if (!qrElement) {
            console.warn("❌ #qr-reader belum siap.");
            return;
        }

        if (isScannerRunning) {
            console.log("Scanner sudah aktif.");
            return;
        }

        html5QrCode = new Html5Qrcode("qr-reader");

        Html5Qrcode.getCameras()
            .then((cameras) => {
                if (!cameras.length) {
                    console.error("Tidak ada kamera terdeteksi.");
                    return;
                }

                const cameraId = cameras[0].id;
                html5QrCode
                    .start(
                        cameraId, {
                            fps: 10
                            , qrbox: (vw, vh) => {
                                let minEdge = Math.min(vw, vh);
                                let size = Math.max(minEdge * 0.7, 150);
                                return {
                                    width: size
                                    , height: size,

                                };
                            }
                        , }, (decodedText) => {
                            if (isProcessing) {
                                console.log("⏳ Masih memproses scan sebelumnya...");
                                return;
                            }
                            const now = Date.now();
                            if (decodedText === lastScannedCode && now - lastScannedTime < 3000) return;

                            isProcessing = true;
                            lastScannedCode = decodedText;
                            lastScannedTime = now;
                            html5QrCode.pause(true);
                            console.log("✅ Scanned:", decodedText);
                            if (window.Livewire) Livewire.dispatch("checkin", {
                                code: decodedText
                            });

                        }, (errorMessage) => {}
                    )
                    .then(() => {
                        isScannerRunning = true;
                        console.log("🎥 Scanner dimulai");
                    })
                    .catch((err) => console.error("Gagal memulai kamera:", err));
            })
            .catch((err) => {
                console.error("Kesalahan akses kamera:", err);
            });
    }

    document.addEventListener("livewire:navigated", () => {
        setTimeout(startScanner, 300);
    });

    window.addEventListener("beforeunload", () => {
        if (isScannerRunning && html5QrCode) {
            // html5QrCode.stop().then(() => console.log("Scanner stopped on unload"));
        }
    });

    function setQrBoxColor(color) {
        const qrShaded = document.querySelector("#qr-shaded-region");
        if (qrShaded) {
            const corners = qrShaded.querySelectorAll("div");
            corners.forEach(corner => {
                corner.style.backgroundColor = color || "rgb(255, 255, 255)";
            });
        }
    }

    document.addEventListener('livewire:initialized', event => {
        Livewire.on('qr-error', (event) => {
            const qrRegion = document.querySelector("#qr-reader > video") ? .parentElement;
            if (qrRegion) {
                qrRegion.style.border = "3px solid #ef4444"; // merah Tailwind
                // clearTimeout(qrRegion._resetTimeout);
                qrRegion._resetTimeout = setTimeout(() => {
                    qrRegion.style.border = "";
                }, 1000);
            }
            setQrBoxColor("#ef4444");
            setTimeout(() => {
                // Reset border container
                if (qrRegion) {
                    qrRegion.style.border = "";
                }
                if (html5QrCode && isScannerRunning) {
                    html5QrCode.resume();
                }
                setQrBoxColor("");

                isProcessing = false;
            }, 1000);
        });
        Livewire.on('qr-success', (event) => {
            const qrRegion = document.querySelector("#qr-reader > video") ? .parentElement;
            if (qrRegion) {
                qrRegion.style.border = "3px solid #22c55e";
                qrRegion._resetTimeout = setTimeout(() => {
                    qrRegion.style.border = "";
                }, 1000);
            }
            setQrBoxColor("#22c55e");
            setTimeout(() => {
                if (qrRegion) {
                    qrRegion.style.border = "";
                }
                if (html5QrCode && isScannerRunning) {
                    html5QrCode.resume();
                }
                setQrBoxColor("");

                isProcessing = false;
                console.log("🔓 Scanner unlocked (error)");
            }, 1000);
        });
    });

</script>
@endpush
