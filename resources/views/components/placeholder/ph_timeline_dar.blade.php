<div class="rounded-2xl border border-slate-200 bg-white overflow-hidden animate-pulse">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <div class="flex items-center gap-3">
            <div class="w-6 h-6 bg-slate-200 rounded"></div>
            <div class="h-4 w-40 bg-slate-200 rounded"></div>
        </div>

        <div class="flex items-center gap-4">
            <div class="h-4 w-24 bg-slate-200 rounded"></div>
            <div class="w-5 h-5 bg-slate-200 rounded"></div>
            <div class="w-5 h-5 bg-slate-200 rounded"></div>
        </div>
    </div>

    {{-- Time Header --}}
    <div class="grid grid-cols-10 text-xs text-slate-400 border-b border-slate-200">
        @for ($i = 0; $i < 10; $i++)
            <div class="py-3 text-center">
                <div class="h-3 w-12 mx-auto bg-slate-200 rounded"></div>
            </div>
        @endfor
    </div>

    {{-- Timeline Body --}}
    <div class="relative p-6 space-y-6">

        {{-- Grid background --}}
        <div class="absolute inset-0 grid grid-cols-10 pointer-events-none">
            @for ($i = 0; $i < 10; $i++)
                <div class="border-r border-dashed border-slate-200"></div>
            @endfor
        </div>

        {{-- Task Rows --}}
        @for ($i = 0; $i < 4; $i++)
            <div class="relative h-12">

                {{-- Task bar --}}
                <div class="absolute top-0 h-9 rounded-xl bg-slate-200"
                     style="left: {{ rand(0, 40) }}%; width: {{ rand(30, 80) }}%;">

                </div>

            </div>
        @endfor

    </div>
</div>
