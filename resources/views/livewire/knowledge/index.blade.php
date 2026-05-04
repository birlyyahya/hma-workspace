<?php

use App\Models\SupportAnnouncement;
use App\Models\SupportArticle;
use App\Models\SupportDocumentation;
use App\Models\SupportPolicy;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $q = '';

    #[Computed]
    public function counts(): array
    {
        return [
            'announcements' => SupportAnnouncement::active()->count(),
            'articles' => SupportArticle::published()->count(),
            'policies' => SupportPolicy::active()->count(),
            'documentation' => SupportDocumentation::active()->count(),
        ];
    }

    #[Computed]
    public function latestAnnouncements()
    {
        return SupportAnnouncement::active()->ongoing()
            ->orderByRaw("CASE WHEN priority = 'important' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function recentArticles()
    {
        return SupportArticle::published()
            ->with('author:id,name,username')
            ->latest('published_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function searchResults(): array
    {
        $term = Str::lower(trim($this->q));

        if ($term === '') {
            return [];
        }

        $like = "%{$term}%";

        return [
            'announcements' => SupportAnnouncement::active()
                ->whereRaw('LOWER(title) LIKE ?', [$like])
                ->limit(5)->get(['id', 'title', 'slug']),
            'articles' => SupportArticle::published()
                ->whereRaw('LOWER(title) LIKE ?', [$like])
                ->limit(5)->get(['id', 'title', 'slug']),
            'policies' => SupportPolicy::active()
                ->whereRaw('LOWER(title) LIKE ?', [$like])
                ->limit(5)->get(['id', 'title', 'slug']),
            'documentation' => SupportDocumentation::active()
                ->whereRaw('LOWER(title) LIKE ?', [$like])
                ->limit(5)->get(['id', 'title', 'slug']),
        ];
    }
}; ?>

<div>
    <div class="relative mb-6 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-2">{{ __('Support Center') }}</flux:heading>
        <flux:subheading>{{ __('Pusat informasi, artikel, kebijakan, dan dokumentasi internal') }}</flux:subheading>
        <flux:separator variant="subtle" class="mt-4" />
    </div>

    <x-settings.knowledge-layout
        :heading="__('Knowledge Hub')"
        :subheading="__('Pintasan ke seluruh konten support center')"
    >
        {{-- GLOBAL SEARCH --}}
        <div class="relative mb-6">
            <flux:input
                icon="magnifying-glass"
                placeholder="{{ __('Cari di seluruh knowledge base...') }}"
                wire:model.live.debounce.300ms="q"
                clearable
            />

            @if (trim($q) !== '')
                @php
                    $results = $this->searchResults;
                    $total = 0;
                    foreach ($results as $g) {
                        $total += $g->count();
                    }
                    $hrefMap = [
                        'articles' => fn ($i) => route('knowledge.articles-show', $i->slug),
                        'announcements' => fn ($i) => route('knowledge.announcements').'#'.$i->slug,
                        'policies' => fn ($i) => route('knowledge.policies').'#'.$i->slug,
                        'documentation' => fn ($i) => route('knowledge.documentation').'#'.$i->slug,
                    ];
                @endphp

                <div class="mt-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @if ($total === 0)
                        <div class="p-5 text-sm text-zinc-500 text-center">
                            Tidak ada hasil untuk "<span class="font-medium">{{ $q }}</span>"
                        </div>
                    @else
                        @foreach ($results as $group => $items)
                            @if ($items->count() > 0)
                                <div class="p-3">
                                    <p class="text-[11px] uppercase tracking-wide text-zinc-400 mb-2 px-2">{{ $group }}</p>
                                    <div class="space-y-1">
                                        @foreach ($items as $item)
                                            <a href="{{ $hrefMap[$group]($item) }}" wire:navigate
                                                class="flex items-center justify-between gap-3 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                                <span class="text-sm text-zinc-800 dark:text-zinc-100 truncate">{{ $item->title }}</span>
                                                <flux:icon name="arrow-right" class="w-4 h-4 text-zinc-400" />
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </div>
            @endif
        </div>

        {{-- MODULE CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            @php
                $modules = [
                    ['title' => 'Announcements', 'desc' => 'Pengumuman & info penting', 'icon' => 'megaphone', 'count' => $this->counts['announcements'], 'route' => route('knowledge.announcements'), 'gradient' => 'from-red-500 to-rose-600', 'bg' => 'from-red-50/60'],
                    ['title' => 'Articles', 'desc' => 'Artikel kontribusi user', 'icon' => 'newspaper', 'count' => $this->counts['articles'], 'route' => route('knowledge.articles'), 'gradient' => 'from-blue-500 to-indigo-600', 'bg' => 'from-blue-50/60'],
                    ['title' => 'Policies & Rules', 'desc' => 'Aturan perusahaan', 'icon' => 'shield-check', 'count' => $this->counts['policies'], 'route' => route('knowledge.policies'), 'gradient' => 'from-amber-500 to-orange-500', 'bg' => 'from-amber-50/60'],
                    ['title' => 'Documentation', 'desc' => 'SOP & panduan kerja', 'icon' => 'book-open', 'count' => $this->counts['documentation'], 'route' => route('knowledge.documentation'), 'gradient' => 'from-emerald-500 to-teal-600', 'bg' => 'from-emerald-50/60'],
                ];
            @endphp
            @foreach ($modules as $m)
                <a href="{{ $m['route'] }}" wire:navigate
                    class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-linear-to-br {{ $m['bg'] }} to-transparent dark:from-transparent p-5 hover:-translate-y-0.5 hover:shadow-lg transition-all">
                    <div class="flex items-start justify-between">
                        <div class="w-11 h-11 rounded-xl bg-linear-to-br {{ $m['gradient'] }} flex items-center justify-center shadow-sm">
                            <flux:icon :name="$m['icon']" class="w-5 h-5 text-white" />
                        </div>
                        <span class="text-2xl font-bold text-zinc-800 dark:text-zinc-100">{{ $m['count'] }}</span>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $m['title'] }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $m['desc'] }}</p>
                    </div>
                    <flux:icon name="arrow-up-right" class="absolute top-4 right-4 w-4 h-4 text-zinc-300 dark:text-zinc-600 opacity-0 group-hover:opacity-100 transition" />
                </a>
            @endforeach
        </div>

        {{-- LATEST ANNOUNCEMENTS --}}
        <section class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Pengumuman Terbaru</h3>
                <a href="{{ route('knowledge.announcements') }}" wire:navigate class="text-xs text-blue-600 hover:underline">Lihat semua</a>
            </div>

            @if ($this->latestAnnouncements->count() === 0)
                <div class="rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 p-6 text-center text-sm text-zinc-500">
                    Belum ada pengumuman aktif
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($this->latestAnnouncements as $a)
                        <a href="{{ route('knowledge.announcements') }}#{{ $a->slug }}" wire:navigate
                            class="block rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 hover:shadow-sm transition">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                                    {{ $a->priority === 'important' ? 'bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' }}">
                                    <flux:icon name="{{ $a->priority === 'important' ? 'fire' : 'megaphone' }}" class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-white truncate">{{ $a->title }}</p>
                                        @if ($a->priority === 'important')
                                            <flux:badge color="red" size="sm">Important</flux:badge>
                                        @endif
                                    </div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 mt-1">
                                        {{ Str::limit(strip_tags($a->content), 160) }}
                                    </p>
                                    <p class="text-[11px] text-zinc-400 mt-1.5">{{ $a->created_at?->diffForHumans() }}</p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- RECENT ARTICLES --}}
        <section>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Artikel Terbaru</h3>
                <a href="{{ route('knowledge.articles') }}" wire:navigate class="text-xs text-blue-600 hover:underline">Lihat semua</a>
            </div>

            @if ($this->recentArticles->count() === 0)
                <div class="rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 p-6 text-center text-sm text-zinc-500">
                    Belum ada artikel dipublish
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($this->recentArticles as $a)
                        <a href="{{ route('knowledge.articles-show', $a->slug) }}" wire:navigate
                            class="group rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden hover:shadow-md transition">
                            @if ($a->featured_image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($a->featured_image) }}"
                                    alt="{{ $a->title }}"
                                    class="w-full h-32 object-cover" />
                            @else
                                <div class="w-full h-32 bg-linear-to-br from-blue-100 to-indigo-100 dark:from-blue-500/10 dark:to-indigo-500/10 flex items-center justify-center">
                                    <flux:icon name="newspaper" class="w-8 h-8 text-blue-400 dark:text-blue-500/50" />
                                </div>
                            @endif
                            <div class="p-4 space-y-2">
                                @if ($a->category)
                                    <flux:badge color="blue" size="sm">{{ $a->category }}</flux:badge>
                                @endif
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white line-clamp-2 group-hover:text-blue-600">{{ $a->title }}</p>
                                @if ($a->excerpt)
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $a->excerpt }}</p>
                                @endif
                                <div class="flex items-center justify-between text-[11px] text-zinc-400 pt-1">
                                    <span class="truncate">{{ $a->author?->name ?? 'Unknown' }}</span>
                                    <span>{{ $a->read_time }} min read</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </x-settings.knowledge-layout>
</div>
