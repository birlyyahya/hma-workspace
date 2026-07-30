{{-- Skeleton loading untuk ca-index (komponen lazy). Meniru struktur dashboard --}}
{{-- agar transisi ke konten asli tidak "melompat". --}}
<div class="space-y-4 animate-pulse" aria-busy="true" aria-label="Memuat data Cash Advance">
    {{-- ================= TOP GRID ================= --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- LEFT: BALANCE + CHART + STATS --}}
        <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="p-6 space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-24 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="h-5 w-14 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="h-5 w-20 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                        </div>
                        <div class="h-10 w-56 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-4 w-40 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>
                    <div class="flex gap-2">
                        <div class="h-8 w-40 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-8 w-24 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>
                </div>

                {{-- CHART --}}
                <div class="h-72 w-full rounded-lg bg-zinc-100 dark:bg-zinc-800"></div>
            </div>

            {{-- INCOME / EXPENSE --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 border-t border-zinc-200 dark:border-zinc-700">
                <div class="p-6 sm:border-e border-zinc-200 dark:border-zinc-700 space-y-3">
                    <div class="h-4 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-7 w-36 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-3 w-44 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
                <div class="p-6 border-t sm:border-t-0 border-zinc-200 dark:border-zinc-700 space-y-3">
                    <div class="h-4 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-7 w-36 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-3 w-44 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
            </div>
        </div>

        {{-- RIGHT: DOMPET KEGIATAN + BREAKDOWN --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="p-6 space-y-4 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between items-center">
                    <div class="h-4 w-32 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-4 w-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
                <div class="space-y-3">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="space-y-2">
                                    <div class="h-3.5 w-28 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                    <div class="h-3 w-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                </div>
                            </div>
                            <div class="h-3.5 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        </div>
                    @endfor
                </div>
                <div class="h-9 w-full rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
            </div>

            {{-- BREAKDOWN PER KATEGORI --}}
            <div class="p-6 space-y-4">
                <div class="h-3.5 w-52 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-7 w-40 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-10 w-full rounded bg-zinc-100 dark:bg-zinc-800"></div>
                <div class="flex flex-wrap gap-3">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="h-3 w-24 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- ================= RECENT TRANSACTION ================= --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
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

        <div class="border-t border-zinc-100 dark:border-zinc-800">
            {{-- header baris --}}
            <div class="flex items-center gap-4 px-6 py-3 bg-zinc-50 dark:bg-zinc-800">
                @for ($i = 0; $i < 6; $i++)
                    <div class="h-3 flex-1 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                @endfor
            </div>
            {{-- baris data --}}
            @for ($row = 0; $row < 5; $row++)
                <div class="flex items-center gap-4 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    @for ($col = 0; $col < 6; $col++)
                        <div class="h-3.5 flex-1 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    @endfor
                </div>
            @endfor
        </div>
    </div>
</div>
