{{--
    Kartu "kantong" dompet kegiatan (carousel ca-index). Dipakai untuk dompet
    milik sendiri maupun dompet tempat user jadi kolaborator — badge
    "Kolaborator" dipasang bila $item['is_collaborator'] true, supaya jelas
    kenapa dompet yang bukan dia buat ikut muncul di daftarnya.

    Variabel: $item (array), $i (index untuk pocketTone), $tone (array dari pocketTone($i))
--}}
@php $isClosed = ($item['status'] ?? 'open') === 'closed'; @endphp
<a wire:key="dompet-{{ $item['kode_ca'] }}" href="{{ route('cashadvance.dompet-show', $item['kode_ca']) }}" wire:navigate
    class="flex w-36 shrink-0 flex-col gap-3 rounded-2xl border p-3.5 snap-start transition hover:-translate-y-0.5 lg:w-auto {{ $isClosed ? 'border-zinc-200 bg-zinc-50 opacity-70 dark:border-zinc-700 dark:bg-zinc-800/60' : $tone['tint'] }}">
    <div class="flex items-center justify-between">
        <flux:icon :name="$isClosed ? 'lock-closed' : 'briefcase'" class="size-4 {{ $isClosed ? 'text-zinc-400' : $tone['text'] }}" />
        <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ $isClosed ? 'Ditutup' : 'Aktif' }}</span>
    </div>
    <div>
        <p class="text-sm font-semibold leading-tight text-zinc-900 dark:text-white line-clamp-2">{{ $item['judul_kegiatan'] }}</p>
        <p class="mt-1.5 text-xs font-bold tabular-nums {{ $isClosed ? 'text-zinc-400' : $tone['text'] }}">
            Rp {{ number_format($item['sisa_saldo'], 0, ',', '.') }}
        </p>
        @if ($item['is_collaborator'] ?? false)
        <span class="mt-1.5 inline-flex w-fit items-center gap-1 rounded-full bg-zinc-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
            <flux:icon name="user-group" class="size-2.5" /> Kolaborator
        </span>
        @endif
    </div>
</a>
