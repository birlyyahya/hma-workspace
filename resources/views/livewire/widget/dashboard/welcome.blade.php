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

    public function getGreetingIconColorProperty(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour < 11 => 'text-amber-500',
            $hour < 18 => 'text-sky-500',
            default => 'text-indigo-500',
        };
    }

    public function getHariProperty(): string
    {
        return now()->translatedFormat('l');
    }

    public function getTanggalProperty(): string
    {
        return now()->translatedFormat('d F Y');
    }

    public function getQuoteProperty(): string
    {
        return strip_tags(Inspiring::quote());
    }
}; ?>

<div
    data-testid="dashboard-welcome-widget"
    class="px-1 py-2"
>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <flux:avatar
                circle
                size="lg"
                class="shrink-0"
                name="{{ auth()->user()->name }}"
                color="auto"
                color:seed="{{ auth()->id() }}"
            />

            <div class="min-w-0">
                <p class="flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    <flux:icon :name="$this->greetingIcon" variant="mini" class="size-4 {{ $this->greetingIconColor }}" />
                    <span>Selamat {{ $this->waktu }},</span>
                </p>
                <h1 class="truncate text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl dark:text-white">
                    {{ auth()->user()->name }}
                </h1>
                <p class="mt-1 line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $this->quote }}
                </p>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3 border-t border-zinc-200/80 pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0 dark:border-zinc-700">
            <div class="flex size-11 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400">
                <flux:icon name="calendar-days" variant="mini" class="size-5" />
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ $this->hari }}</p>
                <p class="text-sm font-semibold text-zinc-900 tabular-nums dark:text-white">{{ $this->tanggal }}</p>
            </div>
        </div>
    </div>
</div>
