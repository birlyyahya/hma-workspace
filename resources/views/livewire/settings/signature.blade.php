<?php

use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.settings.layout')]
class extends Component {

    use WithFileUploads;


    public $signature;
    public $drawSign;

    public function getUserProperty (){

        if(Cache::get('ttd_user_' . Auth::user()->id)){
            return Cache::get('ttd_user_' . Auth::user()->id);
        }

        $response = Http::get(env('API_IZIN') . '/global/user/get-user/'.Auth::user()->username)->json();

        if ($response['success']) {
            Cache::remember(
            'ttd_user_' . Auth::user()->id,now()->addMonths(6), // key unik per user
            function () use ($response) {
                return $response['data']['signature'];
            }
        );
            return $response['data']['signature'];
        }else {
            Toaster::error('Failed to fetch user data from API.');
        }

    }

    public function save(){
        $signatureBase64 = "data:" . $this->signature->getMimeType() . ";base64," . base64_encode(file_get_contents($this->signature->getRealPath()));
        $response = Http::post(env('API_IZIN') . '/global/user/update-signature/'.Auth::user()->username, [
            "base64" => $signatureBase64
        ])->json();

        if($response['success']){
            Toaster::success('Signature berhasil diupdate!');
            Cache::forget('ttd_user_' . Auth::user()->id);
            Flux::modal('upload-signature')->close();
            $this->user = $this->signature;
        } else {
            Toaster::error('Gagal mengupdate signature. Silakan coba lagi.');
            \Log::error('User update failed', [
                'status' => $response['status'] ?? null,
                'body'   => $response['message'] ?? 'No message',
            ]);
            return;
        }
    }
    public function saveDraw(){
        $response = Http::post(env('API_IZIN') . '/global/user/update-signature/'.Auth::user()->username, [
            "base64" => $this->signature
        ])->json();

        if($response['success']){
            Toaster::success('Signature berhasil diupdate!');
            Cache::forget('ttd_user_' . Auth::user()->id);
            Flux::modal('upload-signature')->close();
            $this->user = $this->drawSign;
            $this->signature = null;
        } else {
            Toaster::error('Gagal mengupdate signature. Silakan coba lagi.');
            \Log::error('User update failed', [
                'status' => $response['status'] ?? null,
                'body'   => $response['message'] ?? 'No message',
            ]);
            return;
        }
    }

}

?>
@push('link')
<link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/south-street/jquery-ui.css" rel="stylesheet">
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script src="{{ asset('js/jquery.signature.js') }}"></script>
@endpush

<div class="mt-10 space-y-6 px-1">
    <div class="relative mb-5">
        <flux:heading>{{ __('Signature') }}</flux:heading>
        <flux:subheading>{{ __('Update your signature') }}</flux:subheading>
    </div>

    <div class="space-y-6">
        <div class="w-full h-64 bg-white rounded-sm border border-gray-200 flex items-center justify-center overflow-hidden">
            @if ($signature)
            <img src="{{ $signature->temporaryUrl() }}" class="max-h-full max-w-full object-contain">
            @else
            <img id="signature-frame" src="{{ $this->user }}" class="max-h-full max-w-full object-contain">
            @endif
        </div>
        <div class="flex gap-4">
            <flux:modal.trigger name="upload-signature">
                <flux:button variant="primary">Upload Signature</flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="draw-signature">
                <flux:button id="open-draw-signature">Draw Signature</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:modal name="draw-signature" class="w-[95vw] max-w-xl">
        <div class="space-y-6 w-full">
            <div>
                <flux:heading size="lg">Draw Signature</flux:heading>
                <flux:text class="mt-2">Draw your signature here</flux:text>
            </div>
            <div class="w-full" id="sig"></div>
            <div class="flex justify-between">
                <input type="hidden" id="signature-input" wire:model="signature">
                <flux:button id="clear">Clear</flux:button>
                <flux:button id="save" variant="primary" wire:click="saveDraw">Save</flux:button>
            </div>
        </div>
    </flux:modal>
    <flux:modal name="upload-signature" class="w-[95vw] max-w-xl">
        <div class="space-y-2 w-full flex flex-col">
            <div class="w-full">
                <label for="file_input" class="block text-sm font-medium text-gray-700 mb-6">
                    Upload Signature
                </label>
                <input id="file_input" type="file" wire:model="signature" accept=".svg,.png,.jpg,.jpeg,.gif" class="block w-full text-sm text-gray-700
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-medium
                    file:bg-black file:text-white
                    hover:file:bg-gray-700
                    cursor-pointer
                    border border-gray-300
                    rounded-lg
                    bg-white
                    focus:outline-none focus:ring-2 focus:ring-black focus:border-black">
                <p class="mt-2 text-xs text-gray-500">
                    SVG, PNG, JPG or GIF (max 800×400px)
                </p>
            </div>
            <flux:button variant="primary" color="slate" class="ml-auto" wire:click="save">Save</flux:button>
        </div>
    </flux:modal>

    <style>
        .kbw-signature {
            display: block;
            border: 1px solid #a0a0a0;
            -ms-touch-action: none;
            width: 100% !important;
        }

        .kbw-signature-disabled {
            opacity: 0.35;
        }

        #sig {
            width: 100%;
            height: 200px;
        }

    </style>
</div>

<script>
    document.addEventListener('livewire:navigated', function() {
        $(function() {
            var $sig = $('#sig');
            var options = {
                guideline: true
            };
            var resizeTimer;

            function getSize() {
                var canvas = $sig.find('canvas').get(0);

                return {
                    width: canvas ? canvas.width : $sig.width()
                    , height: canvas ? canvas.height : $sig.height()
                };
            }

            function scaleSignatureData(json, scaleX, scaleY) {
                if (!json) {
                    return null;
                }

                var parsed = typeof json === 'string' ? JSON.parse(json) : json;
                if (!parsed.lines) {
                    return parsed;
                }

                parsed.lines = parsed.lines.map(function(line) {
                    return line.map(function(point) {
                        return [
                            Math.round(point[0] * scaleX * 100) / 100
                            , Math.round(point[1] * scaleY * 100) / 100
                        ];
                    });
                });

                return parsed;
            }

            function initSignature() {
                if (!$sig.data('kbwSignature')) {
                    $sig.signature(options);
                }
            }

            function resizeSignature() {
                if (!$sig.data('kbwSignature')) {
                    initSignature();
                    return;
                }

                var oldSize = getSize();
                var data = $sig.signature('isEmpty') ? null : $sig.signature('toJSON');
                $sig.signature('destroy');
                $sig.signature(options);

                if (data) {
                    var newSize = getSize();
                    var scaleX = oldSize.width ? (newSize.width / oldSize.width) : 1;
                    var scaleY = oldSize.height ? (newSize.height / oldSize.height) : 1;
                    var scaledData = scaleSignatureData(data, scaleX, scaleY);

                    $sig.signature('draw', scaledData);
                }
            }

            initSignature();

            $('#open-draw-signature').on('click', function() {
                setTimeout(resizeSignature, 80);
                setTimeout(resizeSignature, 220);
            });

            $('#clear').on('click', function() {
                $sig.signature('clear');
            });
            $('#save').on('click', function() {
                Flux.modals('draw-signature').close()

                let svg = $sig.signature('toSVG');
                let size = getSize();
                let parser = new DOMParser();
                let doc = parser.parseFromString(svg, 'image/svg+xml');
                let svgEl = doc.documentElement;

                svgEl.setAttribute('width', size.width);
                svgEl.setAttribute('height', size.height);
                svgEl.setAttribute('viewBox', '0 0 ' + size.width + ' ' + size.height);
                svgEl.setAttribute('preserveAspectRatio', 'xMidYMid meet');

                svg = new XMLSerializer().serializeToString(svgEl);

                let base64 = btoa(unescape(encodeURIComponent(svg)));
                let dataUrl = "data:image/svg+xml;base64," + base64;

                $('#signature-frame')
                    .attr('src', dataUrl)
                    .removeClass('hidden');

                @this.set('signature', base64);
                @this.set('drawSign', dataUrl);
            });

            $(window).on('resize orientationchange', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(resizeSignature, 100);
            });
        });

    })

</script>
