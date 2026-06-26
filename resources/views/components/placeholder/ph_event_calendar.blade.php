<div class="bg-white rounded-2xl border border-zinc-200 shadow-xs">
    <div class="grid lg:grid-cols-5 divide-y lg:divide-y-0 lg:divide-x divide-zinc-100 animate-pulse">

        {{-- Calendar --}}
        <div class="!min-w-0 p-5 lg:col-span-3">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-zinc-200"></div>
                    <div class="space-y-2">
                        <div class="h-4 w-32 rounded bg-zinc-200"></div>
                        <div class="h-3 w-40 rounded bg-zinc-200"></div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <div class="h-7 w-16 rounded-lg bg-zinc-200"></div>
                    <div class="size-8 rounded-lg bg-zinc-200"></div>
                    <div class="size-8 rounded-lg bg-zinc-200"></div>
                </div>
            </div>

            {{-- Weekday labels --}}
            <div class="grid grid-cols-7 gap-y-1 mb-2">
                @foreach (range(1, 7) as $d)
                <div class="h-3 w-6 mx-auto rounded bg-zinc-200"></div>
                @endforeach
            </div>

            {{-- Day grid --}}
            <div class="grid grid-cols-7 gap-y-1 auto-rows-fr min-h-82">
                @foreach (range(1, 35) as $cell)
                <div class="size-11 m-auto rounded-full bg-zinc-200"></div>
                @endforeach
            </div>
        </div>

        {{-- Event List --}}
        <div class="min-w-0 p-5 lg:col-span-2 bg-zinc-50/40">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="space-y-2">
                    <div class="h-3 w-16 rounded bg-zinc-200"></div>
                    <div class="h-4 w-36 rounded bg-zinc-200"></div>
                </div>
                <div class="h-5 w-16 rounded-full bg-zinc-200 shrink-0"></div>
            </div>

            <div class="space-y-2.5">
                @foreach (range(1, 3) as $item)
                <div class="rounded-xl border border-zinc-200 bg-white p-3">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 w-1 self-stretch rounded-full bg-zinc-200"></div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div class="h-4 w-2/3 rounded bg-zinc-200"></div>
                                <div class="h-4 w-10 rounded-full bg-zinc-200 shrink-0"></div>
                            </div>
                            <div class="h-3 w-24 rounded bg-zinc-200"></div>
                            <div class="h-3 w-28 rounded bg-zinc-200"></div>
                            <div class="flex -space-x-2 pt-1">
                                <div class="size-5 rounded-full bg-zinc-200"></div>
                                <div class="size-5 rounded-full bg-zinc-200"></div>
                                <div class="size-5 rounded-full bg-zinc-200"></div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
