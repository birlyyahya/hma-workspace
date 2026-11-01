@props([
    'izin',
    'part' => 'badge',
])

@php
    $stage = match (true) {
        (string) ($izin['status'] ?? '') === '3' => 'rejected',
        (int) ($izin['hrd_checked'] ?? 0) === 1 => 'hrd',
        (string) ($izin['status'] ?? '') === '2' => 'it',
        default => 'pending',
    };

    $badge = [
        'pending' => ['color' => 'yellow', 'icon' => 'clock', 'text' => 'Menunggu IT'],
        'it' => ['color' => 'blue', 'icon' => 'check-circle', 'text' => 'Disetujui IT'],
        'hrd' => ['color' => 'green', 'icon' => 'check-badge', 'text' => 'Disetujui HRD'],
        'rejected' => ['color' => 'red', 'icon' => 'x-circle', 'text' => 'Ditolak'],
    ][$stage];

    $itDone = in_array($stage, ['it', 'hrd'], true);
    $hrdDone = $stage === 'hrd';
@endphp

@if ($part === 'badge')
    <flux:badge :color="$badge['color']" :icon="$badge['icon']" size="sm" inset="top bottom" {{ $attributes->class('shrink-0') }}>
        {{ $badge['text'] }}
    </flux:badge>
@elseif ($stage === 'rejected')
    <p {{ $attributes->class('flex items-center gap-1 text-[11px] font-medium text-red-600') }}>
        <flux:icon name="x-mark" variant="micro" class="size-3" />
        Izin dibatalkan
    </p>
@elseif ($part === 'stepper-compact')
    <div {{ $attributes->class('flex items-center gap-1.5 text-[10px] font-semibold tracking-wide') }}>
        <span @class(['text-blue-600' => $stage === 'it', 'text-emerald-600' => $hrdDone, 'text-amber-600' => $stage === 'pending'])>IT</span>
        <span @class(['size-2 rounded-full', 'bg-blue-500' => $stage === 'it', 'bg-emerald-500' => $hrdDone, 'border-2 border-amber-400 bg-white' => $stage === 'pending'])></span>
        <span @class(['h-0.5 w-5 rounded-full', 'bg-blue-300' => $stage === 'it', 'bg-emerald-300' => $hrdDone, 'bg-zinc-200' => $stage === 'pending'])></span>
        <span @class(['size-2 rounded-full', 'bg-emerald-500' => $hrdDone, 'border-2 border-zinc-300 bg-white' => ! $hrdDone])></span>
        <span @class(['text-emerald-600' => $hrdDone, 'text-zinc-400' => ! $hrdDone])>HRD</span>
    </div>
@else
    <div {{ $attributes->class('flex items-start') }}>
        <div class="flex w-14 flex-col items-center gap-1">
            <span class="flex size-5 items-center justify-center rounded-full bg-emerald-500 text-white">
                <flux:icon name="check" variant="micro" class="size-3" />
            </span>
            <span class="text-[10px] font-medium text-zinc-500">Diajukan</span>
        </div>
        <div @class(['mt-2.5 h-0.5 flex-1 rounded-full', 'bg-emerald-300' => $itDone, 'bg-zinc-200' => ! $itDone])></div>
        <div class="flex w-14 flex-col items-center gap-1">
            @if ($itDone)
                <span @class(['flex size-5 items-center justify-center rounded-full text-white', 'bg-blue-500' => $stage === 'it', 'bg-emerald-500' => $hrdDone])>
                    <flux:icon name="check" variant="micro" class="size-3" />
                </span>
            @else
                <span class="relative flex size-5 items-center justify-center">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-300 opacity-60"></span>
                    <span class="relative size-3 rounded-full border-2 border-amber-400 bg-white"></span>
                </span>
            @endif
            <span @class(['text-[10px] font-medium', 'text-blue-600' => $stage === 'it', 'text-emerald-600' => $hrdDone, 'text-zinc-500' => $stage === 'pending'])>IT</span>
        </div>
        <div @class(['mt-2.5 h-0.5 flex-1 rounded-full', 'bg-emerald-300' => $hrdDone, 'bg-zinc-200' => ! $hrdDone])></div>
        <div class="flex w-14 flex-col items-center gap-1">
            @if ($hrdDone)
                <span class="flex size-5 items-center justify-center rounded-full bg-emerald-600 text-white ring-4 ring-emerald-100">
                    <flux:icon name="check" variant="micro" class="size-3" />
                </span>
            @elseif ($stage === 'it')
                <span class="relative flex size-5 items-center justify-center">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-300 opacity-60"></span>
                    <span class="relative size-3 rounded-full border-2 border-amber-400 bg-white"></span>
                </span>
            @else
                <span class="size-5 rounded-full border-2 border-dashed border-zinc-300 bg-white"></span>
            @endif
            <span @class(['text-[10px]', 'font-semibold text-emerald-700' => $hrdDone, 'font-medium text-zinc-400' => ! $hrdDone])>HRD</span>
        </div>
    </div>
@endif
