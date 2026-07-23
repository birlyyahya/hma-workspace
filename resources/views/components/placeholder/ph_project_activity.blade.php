<div class="bg-white rounded-2xl border border-zinc-200 shadow-xs overflow-hidden">
    <div class="animate-pulse">
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 pb-0 sm:p-5 sm:pb-0">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-zinc-200"></div>
                <div class="space-y-2">
                    <div class="h-4 w-32 rounded bg-zinc-200"></div>
                    <div class="h-3 w-44 rounded bg-zinc-200"></div>
                </div>
            </div>
            <div class="h-5 w-24 rounded-full bg-zinc-200 shrink-0"></div>
        </div>

        <div class="grid grid-cols-1 gap-6 p-4 sm:p-5 lg:grid-cols-5 lg:items-center lg:gap-2">
            <div class="mx-auto flex h-52 w-52 items-center justify-center sm:h-60 sm:w-60 lg:col-span-2 lg:h-64 lg:w-full">
                <div class="size-44 rounded-full border-[18px] border-zinc-200 sm:size-48"></div>
            </div>

            <div class="min-w-0 lg:col-span-3">
                <div class="mb-3 h-11 rounded-xl bg-zinc-100"></div>
                <div class="space-y-3">
                    @foreach (range(1, 6) as $item)
                        <div>
                            <div class="flex items-center gap-2.5">
                                <div class="size-2.5 rounded-full bg-zinc-200"></div>
                                <div class="h-3 flex-1 rounded bg-zinc-200"></div>
                                <div class="h-3 w-10 rounded bg-zinc-200"></div>
                            </div>
                            <div class="mt-1.5 ml-5 h-1.5 rounded-full bg-zinc-100"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
