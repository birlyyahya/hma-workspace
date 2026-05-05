<div class="animate-pulse">

    {{-- Storage usage card --}}
    <div class="bg-white border border-slate-200 rounded-2xl p-5 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="shrink-0 w-12 h-12 rounded-xl bg-slate-200"></div>
                <div class="space-y-2">
                    <div class="h-4 w-40 bg-slate-200 rounded"></div>
                    <div class="h-3 w-56 bg-slate-200 rounded"></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-3 w-24 bg-slate-200 rounded"></div>
                <div class="h-6 w-16 bg-slate-200 rounded-full"></div>
            </div>
        </div>

        <div class="mt-4">
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-slate-200 rounded-full" style="width: 45%"></div>
            </div>
        </div>
    </div>

    {{-- Main grid: Sidebar + File list --}}
    <div class="grid lg:grid-cols-4 grid-cols-1 gap-6">

        {{-- Sidebar: folder list --}}
        <div class="space-y-3">
            <div class="bg-white border border-slate-200 rounded-2xl p-4 space-y-2">
                <div class="h-3 w-20 bg-slate-200 rounded mb-2"></div>
                @for ($i = 0; $i < 5; $i++)
                <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg
                    {{ $i === 0 ? 'bg-slate-100' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-4 h-4 bg-slate-200 rounded"></div>
                        <div class="h-3 w-20 bg-slate-200 rounded"></div>
                    </div>
                    <div class="h-3 w-6 bg-slate-200 rounded"></div>
                </div>
                @endfor
            </div>
        </div>

        {{-- File list panel --}}
        <div class="lg:col-span-3 bg-white border border-slate-200 rounded-2xl">

            {{-- Toolbar --}}
            <div class="px-5 py-4 border-b border-slate-200">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="h-4 w-32 bg-slate-200 rounded"></div>
                    <div class="flex items-center gap-2">
                        <div class="h-9 w-44 bg-slate-200 rounded-lg"></div>
                        <div class="h-9 w-9 bg-slate-200 rounded-lg"></div>
                        <div class="h-9 w-9 bg-slate-200 rounded-lg"></div>
                    </div>
                </div>

                {{-- Active filter chips --}}
                <div class="mt-3 flex items-center gap-2">
                    <div class="h-6 w-20 bg-slate-200 rounded-full"></div>
                    <div class="h-6 w-24 bg-slate-200 rounded-full"></div>
                </div>
            </div>

            {{-- File rows --}}
            <div class="p-4 space-y-2">
                @for ($i = 0; $i < 6; $i++)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50">
                    <div class="w-10 h-10 bg-slate-200 rounded-lg"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-1/3 bg-slate-200 rounded"></div>
                        <div class="flex items-center gap-2">
                            <div class="h-2.5 w-20 bg-slate-200 rounded"></div>
                            <div class="h-2.5 w-16 bg-slate-200 rounded"></div>
                            <div class="h-2.5 w-24 bg-slate-200 rounded"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-8 h-8 bg-slate-200 rounded-lg"></div>
                        <div class="w-8 h-8 bg-slate-200 rounded-lg"></div>
                        <div class="w-8 h-8 bg-slate-200 rounded-lg"></div>
                    </div>
                </div>
                @endfor

                {{-- Load more --}}
                <div class="flex items-center justify-center pt-4">
                    <div class="h-9 w-32 bg-slate-200 rounded-lg"></div>
                </div>
            </div>
        </div>
    </div>
</div>
