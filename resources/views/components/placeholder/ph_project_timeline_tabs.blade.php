<div class="space-y-6 animate-pulse">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-5 h-5 bg-slate-200 rounded"></div>
                <div class="h-4 w-40 bg-slate-200 rounded"></div>
            </div>

            <div class="flex items-center gap-2">
                <div class="h-8 w-20 bg-slate-200 rounded-md"></div>
                <div class="h-8 w-24 bg-slate-200 rounded-md"></div>
            </div>
        </div>

        {{-- Month Tabs --}}
        <div class="border-b border-slate-200 bg-slate-50">
            <div class="flex items-center gap-2 px-4 py-2 overflow-hidden">
                @for ($i = 0; $i < 8; $i++)
                <div class="flex items-center gap-2 px-4 py-2 rounded-lg
                    {{ $i === 0 ? 'bg-slate-300' : 'bg-slate-200' }}">
                    <div class="h-3 w-14 bg-slate-100/60 rounded"></div>
                    @if ($i % 3 === 0)
                    <div class="h-3 w-4 bg-slate-100/60 rounded-full"></div>
                    @endif
                </div>
                @endfor
            </div>
        </div>

        {{-- Active Timelines (chips) --}}
        <div class="px-6 py-4 border-b border-slate-200 bg-white">
            <div class="h-3 w-24 bg-slate-200 rounded mb-3"></div>
            <div class="flex flex-wrap gap-2">
                @for ($i = 0; $i < 3; $i++)
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 border border-slate-200">
                    <div class="w-3 h-3 bg-slate-300 rounded"></div>
                    <div class="h-3 w-20 bg-slate-300 rounded"></div>
                    <div class="h-3 w-24 bg-slate-200 rounded"></div>
                </div>
                @endfor
            </div>
        </div>

        {{-- Activity List (vertical timeline) --}}
        <div class="px-6 py-6 bg-white">
            <ol class="relative border-l border-slate-200 ml-3 space-y-6">
                @for ($i = 0; $i < 4; $i++)
                <li class="ml-6 relative">
                    <span class="absolute -left-[33px] flex items-center justify-center w-3.5 h-3.5 rounded-full ring-4 ring-white bg-slate-300"></span>

                    <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2">
                            <div class="h-3 w-32 bg-slate-200 rounded"></div>
                            <div class="h-4 w-16 bg-slate-200 rounded-full"></div>
                        </div>
                        <div class="h-4 w-20 bg-slate-200 rounded-full"></div>
                    </div>

                    <div class="p-4 rounded-xl border border-slate-100 bg-slate-50 space-y-2">
                        <div class="h-4 w-2/5 bg-slate-200 rounded"></div>
                        <div class="space-y-1.5">
                            <div class="h-2.5 w-full bg-slate-200 rounded"></div>
                            <div class="h-2.5 w-3/4 bg-slate-200 rounded"></div>
                        </div>
                        <div class="flex items-center gap-4 pt-1">
                            <div class="h-3 w-24 bg-slate-200 rounded"></div>
                            <div class="h-3 w-32 bg-slate-200 rounded"></div>
                        </div>
                    </div>
                </li>
                @endfor
            </ol>
        </div>

    </div>
</div>
