<?php

use Illuminate\Foundation\Inspiring;
use Livewire\Volt\Component;

new class extends Component {
    public function getWaktuProperty(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour < 11 => 'Pagi',
            $hour < 15 => 'Siang',
            $hour < 18 => 'Sore',
            default => 'Malam',
        };
    }

    public function getGreetingIconProperty(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour < 11 => 'sun',
            $hour < 18 => 'cloud',
            default => 'moon',
        };
    }

    public function getTanggalProperty(): string
    {
        return now()->translatedFormat('l, d F Y');
    }

    public function getQuoteProperty(): string
    {
        return Inspiring::quote();
    }
}; ?>

<div
    data-testid="dashboard-welcome-widget"
    class="relative overflow-hidden rounded-xl border border-white/15 bg-linear-to-br from-red-700 via-red-600 to-rose-600 p-6 shadow-sm"
>
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -right-20 -top-24 size-72 rounded-full bg-white/15 blur-3xl"></div>
        <div class="absolute -left-24 -bottom-28 size-72 rounded-full bg-black/15 blur-3xl"></div>
        <div class="absolute inset-0 bg-linear-to-br from-white/10 to-transparent"></div>
    </div>

    <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0 space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs text-white/90 ring-1 ring-white/20">
                    <flux:icon name="calendar-days" class="size-4" />
                    <span class="truncate">{{ $this->tanggal }}</span>
                </div>

                <div class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs text-white/90 ring-1 ring-white/20">
                    <flux:icon :name="$this->greetingIcon" class="size-4" />
                    <span>Selamat {{ $this->waktu }}</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <flux:avatar
                    circle
                    size="sm"
                    class="ring-2 ring-white/25"
                    name="{{ auth()->user()->name }}"
                    color="auto"
                    color:seed="{{ auth()->id() }}"
                />

                <div class="min-w-0">
                    <flux:heading size="xl" class="text-white leading-tight">
                        {{ auth()->user()->name }}
                    </flux:heading>
                    <flux:text class="text-white/80 text-sm line-clamp-2 italic">
                        {!! $this->quote !!}
                    </flux:text>
                </div>
            </div>
        </div>
    </div>
</div>
