{{-- Skeleton loading untuk ca-index (komponen lazy). Meniru struktur wallet card --}}
{{-- baru agar transisi ke konten asli tidak "melompat". --}}
<div class="space-y-4 animate-pulse" aria-busy="true" aria-label="Memuat data Cash Advance">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- LEFT: MASTER WALLET CARD (saldo + chart menyatu) --}}
        <div class="lg:col-span-2">
            <div class="rounded-2xl bg-ca-ink/90 p-6 space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-2">
                        <div class="h-3 w-32 rounded bg-white/15"></div>
                        <div class="h-3 w-20 rounded bg-white/10"></div>
                    </div>
                    <div class="h-6 w-16 rounded-full bg-white/10"></div>
                </div>
                <div class="space-y-2">
                    <div class="h-3 w-24 rounded bg-white/10"></div>
                    <div class="h-10 w-56 rounded bg-white/15"></div>
                </div>
                <div class="h-32 w-full rounded-lg bg-white/5"></div>
                <div class="flex gap-2">
                    <div class="h-10 flex-1 rounded-xl bg-white/10"></div>
                    <div class="h-10 w-28 rounded-xl bg-white/10"></div>
                </div>
                <div class="grid grid-cols-2 gap-4 border-t border-white/10 pt-4">
                    <div class="space-y-1.5">
                        <div class="h-3 w-20 rounded bg-white/10"></div>
                        <div class="h-4 w-24 rounded bg-white/15"></div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="h-3 w-20 rounded bg-white/10"></div>
                        <div class="h-4 w-24 rounded bg-white/15"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: DOMPET KEGIATAN (carousel) + BREAKDOWN --}}
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="p-5 space-y-3 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between items-center">
                    <div class="h-4 w-32 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-3 w-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
                <div class="no-scrollbar flex gap-3 overflow-x-auto lg:flex-wrap lg:overflow-visible">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="w-36 shrink-0 rounded-2xl bg-zinc-100 dark:bg-zinc-800 p-3.5 space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="size-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                                <div class="h-2.5 w-10 rounded bg-zinc-300 dark:bg-zinc-600"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="h-3 w-24 rounded bg-zinc-300 dark:bg-zinc-600"></div>
                                <div class="h-3.5 w-20 rounded bg-zinc-300 dark:bg-zinc-600"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="p-5 space-y-4">
                <div class="h-3.5 w-52 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-7 w-40 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800"></div>
                <div class="flex flex-wrap gap-3">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="h-3 w-24 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- RECENT TRANSACTION --}}
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="space-y-2">
                <div class="h-5 w-44 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 w-28 rounded bg-zinc-200 dark:bg-zinc-700"></div>
            </div>
            <div class="flex gap-2">
                <div class="h-9 w-64 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-9 w-9 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-9 w-28 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-9 w-28 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
            </div>
        </div>

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border-t border-zinc-100 dark:border-zinc-800">
            @for ($row = 0; $row < 5; $row++)
                <div class="flex items-center gap-3 px-4 py-3 md:px-6">
                    <div class="size-10 shrink-0 rounded-xl bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3.5 w-40 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-3 w-28 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>
                    <div class="h-3.5 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
