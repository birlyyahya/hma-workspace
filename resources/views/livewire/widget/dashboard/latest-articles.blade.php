<?php

use App\Models\SupportArticle;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    #[Computed]
    public function items()
    {
        return SupportArticle::published()
            ->with('author:id,name,username')
            ->latest('published_at')
            ->limit(3)
            ->get();
    }

    /**
     * @return array{badge: string, icon: string}
     */
    public function tagStyle(?string $category): array
    {
        $map = [
            'guide' => ['badge' => 'bg-blue-100 text-blue-600', 'icon' => 'text-blue-400'],
            'productivity' => ['badge' => 'bg-green-100 text-green-600', 'icon' => 'text-green-400'],
            'documentation' => ['badge' => 'bg-purple-100 text-purple-600', 'icon' => 'text-purple-400'],
        ];

        return $map[Str::lower((string) $category)] ?? ['badge' => 'bg-zinc-100 text-zinc-600', 'icon' => 'text-zinc-400'];
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-xs space-y-5">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-xl bg-blue-50 ring-1 ring-blue-100 flex items-center justify-center">
                <flux:icon name="newspaper" class="size-5 text-blue-600" />
            </div>
            <div>
                <flux:heading size="lg" class="text-zinc-900 leading-tight">Latest Articles</flux:heading>
                <flux:description class="text-xs text-zinc-500">Bacaan & panduan terbaru</flux:description>
            </div>
        </div>
        <a href="{{ route('knowledge.articles') }}" wire:navigate
            class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
            Lihat semua <flux:icon name="arrow-right" class="size-3.5" />
        </a>
    </div>

    @if ($this->items->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-200 p-8 text-center">
            <flux:icon name="newspaper" class="size-6 mx-auto text-zinc-300" />
            <p class="text-sm text-zinc-500 mt-2">Belum ada artikel</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->items as $article)
                @php($style = $this->tagStyle($article->category))
                <a href="{{ route('knowledge.articles-show', $article->slug) }}" wire:navigate
                    wire:key="dash-art-{{ $article->id }}"
                    class="group rounded-xl border border-zinc-100 bg-zinc-50/50 hover:bg-zinc-50 hover:border-zinc-200 p-4 transition block">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs px-2 py-1 rounded-md {{ $style['badge'] }}">
                            {{ $article->category ?: 'Umum' }}
                        </span>
                        <flux:icon name="arrow-up-right" class="size-4 {{ $style['icon'] }} group-hover:text-zinc-600 transition" />
                    </div>
                    <h3 class="font-semibold text-zinc-900 text-sm leading-snug line-clamp-2">{{ $article->title }}</h3>
                    <p class="text-xs text-zinc-500 mt-2 line-clamp-2">
                        {{ $article->excerpt ?: Str::limit(strip_tags($article->content), 120) }}
                    </p>
                    <div class="flex items-center justify-between mt-4 text-xs text-zinc-400">
                        <span>{{ $article->author?->name ?? 'Admin' }}</span>
                        <span>{{ $article->published_at?->diffForHumans() ?? $article->created_at?->diffForHumans() }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
