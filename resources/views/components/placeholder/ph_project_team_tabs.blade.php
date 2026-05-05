<div class="space-y-6 animate-pulse">

    {{-- TOOLBAR --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="h-10 w-full sm:max-w-sm bg-slate-200 rounded-lg"></div>
        <div class="hidden sm:flex items-center gap-2">
            <div class="h-6 w-24 bg-slate-200 rounded-full"></div>
            <div class="h-6 w-28 bg-slate-200 rounded-full"></div>
        </div>
    </div>

    {{-- TIM INTERNAL --}}
    <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">

        {{-- Section header --}}
        <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-200"></div>
                <div class="space-y-2">
                    <div class="h-3 w-28 bg-slate-200 rounded"></div>
                    <div class="h-2.5 w-32 bg-slate-200 rounded"></div>
                </div>
            </div>
            <div class="h-8 w-32 bg-slate-200 rounded-md"></div>
        </header>

        {{-- Member cards grid --}}
        <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @for ($i = 0; $i < 4; $i++)
                <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white">

                    {{-- Top accent bar --}}
                    <div class="h-1 w-full bg-slate-200"></div>

                    <div class="p-4">
                        {{-- Avatar --}}
                        <div class="w-14 h-14 rounded-full bg-slate-200"></div>

                        {{-- Name + email --}}
                        <div class="mt-3 space-y-2">
                            <div class="h-3.5 w-32 bg-slate-200 rounded"></div>
                            <div class="h-2.5 w-40 bg-slate-200 rounded"></div>
                        </div>

                        {{-- Footer badge --}}
                        <div class="mt-4 pt-3 border-t border-dashed border-slate-200">
                            <div class="h-5 w-20 bg-slate-200 rounded-md"></div>
                        </div>
                    </div>
                </article>
                @endfor
            </div>
        </div>
    </section>

    {{-- TIM PENDUKUNG --}}
    <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">

        {{-- Section header --}}
        <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-200"></div>
                <div class="space-y-2">
                    <div class="h-3 w-36 bg-slate-200 rounded"></div>
                    <div class="h-2.5 w-24 bg-slate-200 rounded"></div>
                </div>
            </div>
            <div class="h-8 w-28 bg-slate-200 rounded-md"></div>
        </header>

        {{-- Support team cards --}}
        <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @for ($i = 0; $i < 3; $i++)
                <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="h-1 w-full bg-slate-200"></div>
                    <div class="p-4">
                        <div class="w-14 h-14 rounded-full bg-slate-200"></div>
                        <div class="mt-3 space-y-2">
                            <div class="h-3.5 w-28 bg-slate-200 rounded"></div>
                            <div class="h-2.5 w-32 bg-slate-200 rounded"></div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-dashed border-slate-200 flex items-center justify-between">
                            <div class="h-5 w-12 bg-slate-200 rounded-md"></div>
                            <div class="h-2.5 w-24 bg-slate-200 rounded"></div>
                        </div>
                    </div>
                </article>
                @endfor
            </div>
        </div>
    </section>
</div>
