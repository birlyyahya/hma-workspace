<?php

use App\Models\SupportAnnouncement;
use App\Models\SupportArticle;
use App\Models\SupportDocumentation;
use App\Models\SupportPolicy;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return list<array{icon: string, title: string, desc: string, route: string, count: int}>
     */
    #[Computed]
    public function sections(): array
    {
        return [
            ['icon' => 'book-open', 'title' => 'Documentation / Guides', 'desc' => 'SOP & panduan kerja', 'route' => 'knowledge.documentation', 'count' => SupportDocumentation::active()->count()],
            ['icon' => 'shield-check', 'title' => 'Policies & Rules', 'desc' => 'Aturan & kebijakan perusahaan', 'route' => 'knowledge.policies', 'count' => SupportPolicy::active()->count()],
            ['icon' => 'newspaper', 'title' => 'Articles', 'desc' => 'Artikel kontribusi user', 'route' => 'knowledge.articles', 'count' => SupportArticle::published()->count()],
            ['icon' => 'megaphone', 'title' => 'Announcements', 'desc' => 'Pengumuman & info penting', 'route' => 'knowledge.announcements', 'count' => SupportAnnouncement::active()->count()],
        ];
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-xs space-y-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-xl bg-zinc-100 ring-1 ring-zinc-200 flex items-center justify-center">
                <flux:icon name="book-open" class="size-5 text-zinc-700" />
            </div>
            <div>
                <flux:heading size="lg" class="text-zinc-900 leading-tight">Knowledge Hub</flux:heading>
                <flux:description class="text-xs text-zinc-500">Dokumentasi & SOP perusahaan</flux:description>
            </div>
        </div>
        <a href="{{ route('knowledge') }}" wire:navigate
            class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
            Explore <flux:icon name="arrow-right" class="size-3.5" />
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ($this->sections as $section)
            <a href="{{ route($section['route']) }}" wire:navigate wire:key="hub-{{ $loop->index }}"
                class="flex items-center justify-between gap-3 rounded-xl border border-zinc-100 p-3 hover:border-zinc-200 hover:bg-zinc-50 transition">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-9 rounded-lg bg-zinc-100 flex items-center justify-center shrink-0">
                        <flux:icon :name="$section['icon']" class="size-4.5 text-zinc-600" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-zinc-900 truncate">{{ $section['title'] }}</p>
                        <p class="text-xs text-zinc-500 truncate">{{ $section['desc'] }}</p>
                    </div>
                </div>
                <span class="shrink-0 text-xs font-semibold text-zinc-700 bg-zinc-100 rounded-full px-2 py-0.5 min-w-7 text-center">
                    {{ $section['count'] }}
                </span>
            </a>
        @endforeach
    </div>
</div>
