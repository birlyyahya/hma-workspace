<?php

use App\Models\SupportAnnouncement;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    #[Computed]
    public function items()
    {
        return SupportAnnouncement::active()->ongoing()
            ->orderByRaw("CASE WHEN priority = 'important' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(3)
            ->get();
    }
}; ?>

<div>
    @if ($this->items->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 text-center">
            <flux:icon name="megaphone" class="w-6 h-6 mx-auto text-zinc-300" />
            <p class="text-sm text-zinc-500 mt-2">Tidak ada pengumuman aktif</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->items as $item)
                <a href="{{ route('knowledge.announcements') }}#{{ $item->slug }}" wire:navigate
                    wire:key="dash-ann-{{ $item->id }}"
                    class="block relative overflow-hidden rounded-xl p-5 shadow-sm bg-white border
                    {{ $item->priority === 'important' ? 'border-red-100 dark:border-red-500/20' : 'border-zinc-100 dark:border-zinc-700' }}
                    hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="p-2 rounded-lg flex h-fit items-center justify-center
                                {{ $item->priority === 'important' ? 'bg-red-500' : 'bg-blue-500' }}">
                                <flux:icon name="{{ $item->priority === 'important' ? 'fire' : 'megaphone' }}" class="w-5 h-5 text-white" />
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <flux:heading size="base" class="font-semibold text-gray-900 dark:text-white">
                                        {{ $item->title }}
                                    </flux:heading>
                                    @if ($item->priority === 'important')
                                        <flux:badge color="red" class="text-xs">Important</flux:badge>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($item->content), 200) }}
                                </p>
                                @if ($item->start_date || $item->end_date)
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ $item->start_date?->format('d M Y') ?? '...' }} – {{ $item->end_date?->format('d M Y') ?? '...' }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <flux:icon name="arrow-right" class="w-4 h-4 text-zinc-400 shrink-0 mt-2" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
