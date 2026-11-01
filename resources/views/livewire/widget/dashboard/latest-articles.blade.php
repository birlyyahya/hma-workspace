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
}; ?>

<div class="bg-white rounded-2xl shadow-sm">
    <div class="flex items-baseline justify-between gap-4 px-5 pt-5 sm:px-6 sm:pt-6">
        <h2 class="text-base font-semibold tracking-tight text-zinc-900">Artikel Terbaru</h2>
        <a href="{{ route('knowledge.articles') }}" wire:navigate
            class="text-xs font-medium text-zinc-500 transition-colors hover:text-accent-content focus-visible:outline-2 focus-visible:outline-accent">
            Lihat semua
        </a>
    </div>

    @if ($this->items->isEmpty())
        <div class="px-5 py-14 text-center sm:px-6">
            <p class="text-sm font-medium text-zinc-700">Belum ada artikel</p>
            <p class="mt-1 text-xs text-zinc-500">Artikel yang dipublikasikan akan muncul di sini</p>
        </div>
    @else
        <ul class="mt-3 divide-y divide-zinc-100 px-5 pb-2 sm:px-6">
            @foreach ($this->items as $article)
                @php
                    $excerpt = $article->excerpt ?: Str::limit(strip_tags($article->content), 140);
                    $time = $article->published_at ?? $article->created_at;
                @endphp
                <li wire:key="dash-art-{{ $article->id }}">
                    <a href="{{ route('knowledge.articles-show', $article->slug) }}" wire:navigate
                        class="group grid gap-x-8 gap-y-1 py-4 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent md:grid-cols-[minmax(0,1fr)_11rem]">
                        <div class="min-w-0">
                            <h3 @class([
                                'font-semibold leading-snug text-zinc-900 transition-colors group-hover:text-accent-content',
                                'text-base' => $loop->first,
                                'text-sm' => ! $loop->first,
                            ])>{{ $article->title }}</h3>
                            @if ($excerpt !== '')
                                <p @class([
                                    'mt-1 text-sm text-zinc-500',
                                    'line-clamp-2' => $loop->first,
                                    'line-clamp-1' => ! $loop->first,
                                ])>{{ $excerpt }}</p>
                            @endif
                        </div>
                        <p class="text-xs text-zinc-500 md:text-end">
                            <span class="text-zinc-700">{{ $article->category ?: 'Umum' }}</span>
                            <span class="md:block">
                                <span class="md:hidden" aria-hidden="true">&middot;</span>
                                {{ $article->author?->name ?? 'Admin' }}
                            </span>
                            <time class="md:block" datetime="{{ $time?->toIso8601String() }}">
                                <span class="md:hidden" aria-hidden="true">&middot;</span>
                                {{ $time?->diffForHumans() }}
                            </time>
                        </p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
