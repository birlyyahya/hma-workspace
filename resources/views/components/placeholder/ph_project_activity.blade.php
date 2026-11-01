<div class="bg-white rounded-2xl shadow-sm">
    <div class="animate-pulse">
        <div class="flex flex-wrap items-baseline justify-between gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
            <div class="h-4 w-36 rounded bg-zinc-200"></div>
            <div class="h-3 w-44 rounded bg-zinc-200"></div>
        </div>

        <div class="grid grid-cols-1 gap-6 px-5 pb-5 pt-4 sm:px-6 sm:pb-6 lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-center lg:gap-10">
            <div class="mx-auto flex size-56 items-center justify-center lg:size-64">
                <div class="size-48 rounded-full border-[18px] border-zinc-200 lg:size-52"></div>
            </div>

            <div class="min-w-0 divide-y divide-zinc-100">
                @foreach (range(1, 6) as $item)
                    <div class="flex items-center gap-3 py-3">
                        <div class="size-2.5 rounded-full bg-zinc-200"></div>
                        <div class="h-3 flex-1 rounded bg-zinc-200"></div>
                        <div class="h-3 w-5 rounded bg-zinc-200"></div>
                        <div class="h-3 w-10 rounded bg-zinc-200"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
