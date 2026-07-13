<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">Notification Settings</flux:heading>

    <x-settings.layout heading="Notifikasi" subheading="Kelola izin notifikasi browser & web push di perangkat ini">
        <div
            class="space-y-6"
            x-data="{
                supported: 'Notification' in window && 'PushManager' in window && 'serviceWorker' in navigator,
                permission: 'Notification' in window ? Notification.permission : 'unsupported',
                subscribed: false,
                currentEndpoint: null,
                expanded: false,
                busy: false,
                async check() {
                    if (! this.supported) return;
                    this.permission = Notification.permission;
                    const registration = await navigator.serviceWorker.getRegistration();
                    const subscription = registration ? await registration.pushManager.getSubscription() : null;
                    this.subscribed = !! subscription;
                    this.currentEndpoint = subscription ? subscription.endpoint : null;
                },
                async enable() {
                    this.busy = true;
                    try {
                        if (window.registerWebPush) {
                            await window.registerWebPush();
                        }
                        await this.check();
                        $wire.$refresh();
                    } finally {
                        this.busy = false;
                    }
                },
            }"
            x-init="check()"
        >
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:text>Dukungan browser</flux:text>
                    <flux:badge x-show="supported" color="green" size="sm">Didukung</flux:badge>
                    <flux:badge x-show="! supported" color="amber" size="sm">Tidak didukung</flux:badge>
                </div>

                <div class="flex items-center justify-between">
                    <flux:text>Izin notifikasi</flux:text>
                    <flux:badge x-show="permission === 'granted'" color="green" size="sm">Diizinkan</flux:badge>
                    <flux:badge x-show="permission === 'denied'" color="red" size="sm">Diblokir</flux:badge>
                    <flux:badge x-show="permission === 'default'" color="zinc" size="sm">Belum diminta</flux:badge>
                    <flux:badge x-show="permission === 'unsupported'" color="amber" size="sm">-</flux:badge>
                </div>

                <div class="flex items-center justify-between">
                    <flux:text>Langganan perangkat ini</flux:text>
                    <flux:badge x-show="subscribed" color="green" size="sm">Aktif</flux:badge>
                    <flux:badge x-show="! subscribed" color="zinc" size="sm">Belum aktif</flux:badge>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div>
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between"
                    x-on:click="expanded = ! expanded"
                >
                    <flux:text>Perangkat terdaftar di akunmu</flux:text>
                    <span class="flex items-center gap-2">
                        <flux:badge color="blue" size="sm">{{ $this->deviceCount }} perangkat</flux:badge>
                        <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform" x-bind:class="expanded && 'rotate-180'" />
                    </span>
                </button>

                <div x-show="expanded" x-collapse x-cloak>
                    <div class="mt-3 space-y-3">
                        @forelse ($this->devices as $device)
                            <div
                                wire:key="device-{{ $device['id'] }}"
                                class="rounded-lg border p-3"
                                x-bind:class="currentEndpoint === @js($device['endpoint'])
                                    ? 'border-green-400 bg-green-50 dark:border-green-600 dark:bg-green-900/10'
                                    : 'border-zinc-200 dark:border-zinc-700'"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $device['name'] }}</flux:heading>
                                        <flux:badge x-show="currentEndpoint === @js($device['endpoint'])" x-cloak color="green" size="sm" icon="check-circle">
                                            Perangkat ini
                                        </flux:badge>
                                    </div>
                                    <flux:button
                                        variant="danger"
                                        size="sm"
                                        icon="trash"
                                        class="cursor-pointer"
                                        wire:click="confirmRemoveDevice({{ $device['id'] }})"
                                    />
                                </div>
                                <div class="mt-2 space-y-1">
                                    <flux:text class="text-sm">Layanan push: {{ $device['service'] }}</flux:text>
                                    <flux:text class="text-sm">Terdaftar: {{ $device['subscribed_at'] }}</flux:text>
                                    <flux:text class="text-sm">Terakhir aktif: {{ $device['last_seen'] }}</flux:text>
                                </div>
                            </div>
                        @empty
                            <flux:text class="text-sm">Belum ada perangkat yang terdaftar.</flux:text>
                        @endforelse
                    </div>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <flux:callout x-show="! supported" x-cloak icon="exclamation-triangle" color="amber">
                <flux:callout.text>
                    Browser ini tidak mendukung web push. Di iPhone/iPad, buka situs lewat Safari lalu
                    <strong>Add to Home Screen</strong> dan jalankan dari ikon Home Screen (butuh iOS 16.4+).
                </flux:callout.text>
            </flux:callout>

            <flux:callout x-show="permission === 'denied'" x-cloak icon="bell-slash" color="red">
                <flux:callout.text>
                    Notifikasi diblokir, dan browser tidak mengizinkan situs memunculkan prompt lagi.
                    Buka pengaturan situs di browser (ikon gembok di address bar &rarr; Notifications &rarr; Allow/Ask),
                    atau di iOS: Settings &rarr; Notifications &rarr; HMA Workspace, lalu muat ulang halaman ini.
                </flux:callout.text>
            </flux:callout>

            <div class="flex gap-3">
                <flux:button
                    x-show="supported && permission === 'default'"
                    x-cloak
                    variant="primary"
                    icon="bell"
                    x-on:click="enable()"
                    x-bind:disabled="busy"
                >
                    <span x-show="! busy">Minta izin notifikasi</span>
                    <span x-show="busy">Menunggu izin...</span>
                </flux:button>

                <flux:button
                    x-show="supported && permission === 'granted' && ! subscribed"
                    x-cloak
                    variant="primary"
                    icon="bell"
                    x-on:click="enable()"
                    x-bind:disabled="busy"
                >
                    Aktifkan di perangkat ini
                </flux:button>

                <flux:button
                    x-show="subscribed"
                    x-cloak
                    variant="primary"
                    icon="bell-alert"
                    wire:click="sendTestNotification"
                    wire:loading.attr="disabled"
                >
                    Kirim notifikasi tes
                </flux:button>
            </div>

            <flux:modal name="confirm-remove-device" class="md:w-96">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Hapus perangkat?</flux:heading>
                        <flux:text class="mt-2">
                            <strong>{{ $this->pendingDeleteDeviceName ?? 'Perangkat ini' }}</strong> tidak akan menerima
                            notifikasi lagi sampai subscribe ulang.
                        </flux:text>
                    </div>
                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost" class="cursor-pointer">Batal</flux:button>
                        </flux:modal.close>
                        <flux:button
                            variant="danger"
                            class="cursor-pointer"
                            wire:click="removeDevice"
                            wire:loading.attr="disabled"
                        >
                            Hapus perangkat
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    </x-settings.layout>
</section>
