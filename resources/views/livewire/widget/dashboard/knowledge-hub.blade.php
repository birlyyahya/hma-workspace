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

<div class="bg-white rounded-2xl shadow-sm">
    <div class="flex items-baseline justify-between gap-4 px-5 pt-5 sm:px-6 sm:pt-6">
        <h2 class="text-base font-semibold tracking-tight text-zinc-900">Knowledge Hub</h2>
        <a href="{{ route('knowledge') }}" wire:navigate
            class="text-xs font-medium text-zinc-500 transition-colors hover:text-accent-content focus-visible:outline-2 focus-visible:outline-accent">
            Jelajahi
        </a>
    </div>

    <div class="grid grid-cols-1 gap-x-8 px-5 pb-3 pt-3 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        @foreach ($this->sections as $section)
            <a href="{{ route($section['route']) }}" wire:navigate wire:key="hub-{{ $loop->index }}"
                class="group border-t border-zinc-100 py-3.5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="min-w-0 truncate text-sm font-semibold text-zinc-900 transition-colors group-hover:text-accent-content">{{ $section['title'] }}</p>
                    <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ $section['count'] }}</span>
                </div>
                <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $section['desc'] }}</p>
            </a>
        @endforeach
    </div>
</div>
