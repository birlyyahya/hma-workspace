<div class="rounded-2xl border border-slate-200 bg-white overflow-hidden animate-pulse">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <div class="flex items-center gap-3">
            <div class="w-6 h-6 bg-slate-200 rounded"></div>
            <div class="h-4 w-44 bg-slate-200 rounded"></div>
        </div>

        <div class="flex items-center gap-3">
            <div class="h-4 w-28 bg-slate-200 rounded"></div>
            <div class="w-5 h-5 bg-slate-200 rounded"></div>
            <div class="w-5 h-5 bg-slate-200 rounded"></div>
            <div class="w-5 h-5 bg-slate-200 rounded"></div>
        </div>
    </div>

    {{-- Month Header --}}
    <div class="grid grid-cols-12 text-xs border-b border-slate-200 bg-slate-50">
        @for ($i = 0; $i < 12; $i++)
        <div class="py-3 text-center">
            <div class="h-3 w-12 mx-auto bg-slate-200 rounded"></div>
        </div>
        @endfor
    </div>

    {{-- Gantt Body --}}
    <div class="relative px-6 py-6 space-y-5">

        {{-- Vertical grid lines --}}
        <div class="absolute inset-0 grid grid-cols-12 pointer-events-none">
            @for ($i = 0; $i < 12; $i++)
            <div class="border-r border-dashed border-slate-200"></div>
            @endfor
        </div>

        {{-- Task bars --}}
        @php
        $bars = [
        ['left' => 0, 'width' => 30],
        ['left' => 15, 'width' => 45],
        ['left' => 25, 'width' => 60],
        ['left' => 40, 'width' => 35],
        ['left' => 55, 'width' => 40],
        ];
        @endphp

        @foreach ($bars as $bar)
        <div class="relative h-16">
            <div class="absolute top-0 h-full rounded-xl border-l-4 border-slate-300 bg-white shadow-sm px-4 py-2 flex flex-col justify-between"
                style="left: {{ $bar['left'] }}%; width: {{ $bar['width'] }}%;">
                <div class="flex items-center justify-between">
                    <div class="h-2.5 w-10 bg-slate-200 rounded-full"></div>
                    <div class="w-5 h-5 bg-slate-200 rounded-full"></div>
                </div>
                <div class="h-3 w-3/5 bg-slate-200 rounded"></div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-slate-200 rounded"></div>
                    <div class="h-2.5 w-32 bg-slate-200 rounded"></div>
                </div>
            </div>
        </div>
        @endforeach

    </div>

    {{-- Footer / legend strip --}}
    <div class="flex items-center gap-4 px-6 py-3 border-t border-slate-200 bg-slate-50">
        @for ($i = 0; $i < 4; $i++)
        <div class="flex items-center gap-2">
            <div class="w-3 h-3 bg-slate-200 rounded-full"></div>
            <div class="h-2.5 w-16 bg-slate-200 rounded"></div>
        </div>
        @endfor
    </div>
</div>
