<div>
    <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 h-full flex flex-col animate-pulse">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="shrink-0 size-10 rounded-xl bg-zinc-200"></div>
                <div class="min-w-0 space-y-2">
                    <div class="h-4 w-32 rounded bg-zinc-200"></div>
                    <div class="h-3 w-40 rounded bg-zinc-200"></div>
                </div>
            </div>
            <div class="h-5 w-16 rounded-full bg-zinc-200 shrink-0"></div>
        </div>

        {{-- Tab toggle --}}
        <div class="mt-5 h-10 w-full rounded-xl bg-zinc-100"></div>

        {{-- Hero stat --}}
        <div class="mt-6 flex items-end justify-between gap-4">
            <div class="min-w-0 space-y-2">
                <div class="h-3 w-24 rounded bg-zinc-200"></div>
                <div class="h-10 w-20 rounded bg-zinc-200"></div>
            </div>
            <div class="space-y-2 text-right">
                <div class="h-3 w-20 rounded bg-zinc-200 ml-auto"></div>
                <div class="h-6 w-14 rounded bg-zinc-200 ml-auto"></div>
            </div>
        </div>

        {{-- Stacked progress bar --}}
        <div class="mt-4">
            <div class="h-2.5 w-full rounded-full bg-zinc-200"></div>
        </div>

        {{-- Legend / mini stats --}}
        <div class="mt-5 grid grid-cols-3 gap-3">
            @foreach ([1, 2, 3] as $item)
            <div class="rounded-xl border border-zinc-200 p-3">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-zinc-200"></span>
                    <span class="h-3 w-14 rounded bg-zinc-200"></span>
                </div>
                <div class="mt-1.5 flex items-baseline justify-between gap-1">
                    <span class="h-5 w-8 rounded bg-zinc-200"></span>
                    <span class="h-3 w-8 rounded bg-zinc-200"></span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
