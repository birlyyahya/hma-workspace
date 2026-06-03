<div class="animate-pulse">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ============ LEFT: SPECTECH LIST ============ --}}
        <div class="space-y-4 lg:col-span-3">

            {{-- List header --}}
            <div class="flex items-center justify-between">
                <div class="space-y-2">
                    <div class="h-5 w-40 bg-slate-200 rounded"></div>
                    <div class="h-3 w-28 bg-slate-200 rounded"></div>
                </div>
                <div class="h-8 w-24 bg-slate-200 rounded-md"></div>
            </div>

            {{-- Spectech cards --}}
            @for ($i = 0; $i < 3; $i++)
            <div class="bg-white border border-slate-200 rounded-xl p-6">
                {{-- Card header --}}
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-2 flex-1">
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-44 bg-slate-200 rounded"></div>
                            <div class="h-4 w-16 bg-slate-200 rounded-full"></div>
                        </div>
                        <div class="h-3 w-1/2 bg-slate-200 rounded"></div>
                    </div>
                    <div class="w-6 h-6 bg-slate-200 rounded"></div>
                </div>

                {{-- Stats grid --}}
                <div class="grid grid-cols-3 gap-3 mt-5">
                    @for ($s = 0; $s < 3; $s++)
                    <div class="rounded-lg bg-slate-100 p-3 space-y-2">
                        <div class="h-2.5 w-20 bg-slate-200 rounded"></div>
                        <div class="h-4 w-24 bg-slate-200 rounded"></div>
                    </div>
                    @endfor
                </div>

                {{-- Progress --}}
                <div class="mt-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="h-2.5 w-32 bg-slate-200 rounded"></div>
                        <div class="h-2.5 w-10 bg-slate-200 rounded"></div>
                    </div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-slate-200 rounded-full" style="width: {{ [30, 60, 85][$i] }}%"></div>
                    </div>
                </div>

                {{-- Image thumbnails --}}
                @if ($i === 0)
                <div class="mt-4 flex gap-2 flex-wrap">
                    @for ($t = 0; $t < 4; $t++)
                    <div class="w-14 h-14 rounded-lg bg-slate-200 ring-1 ring-slate-200"></div>
                    @endfor
                </div>
                @endif
            </div>
            @endfor
        </div>

        {{-- ============ RIGHT: SUMMARY ============ --}}
        <div class="space-y-4 hidden">

            {{-- Progress widget --}}
            <div class="bg-white rounded-xl p-6 border border-slate-200 space-y-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="h-4 w-36 bg-slate-200 rounded"></div>
                    <div class="h-8 w-20 bg-slate-200 rounded-md"></div>
                </div>

                {{-- Big % --}}
                <div class="text-center py-2 space-y-2">
                    <div class="h-10 w-24 bg-slate-200 rounded mx-auto"></div>
                    <div class="h-3 w-40 bg-slate-200 rounded mx-auto"></div>
                </div>

                {{-- Progress bar --}}
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-slate-200 rounded-full" style="width: 55%"></div>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                    <div class="space-y-2">
                        <div class="h-2.5 w-20 bg-slate-200 rounded"></div>
                        <div class="h-4 w-28 bg-slate-200 rounded"></div>
                    </div>
                    <div class="space-y-2 text-right">
                        <div class="h-2.5 w-20 bg-slate-200 rounded ml-auto"></div>
                        <div class="h-4 w-28 bg-slate-200 rounded ml-auto"></div>
                    </div>
                </div>
            </div>

            {{-- Formula explainer --}}
            <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-slate-200 rounded"></div>
                    <div class="h-3 w-32 bg-slate-200 rounded"></div>
                </div>
                <div class="w-4 h-4 bg-slate-200 rounded"></div>
            </div>
        </div>
    </div>
</div>
