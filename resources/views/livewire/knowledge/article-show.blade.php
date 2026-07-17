<?php

use App\Models\SupportArticle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app')]
class extends Component {
    public SupportArticle $article;

    public function mount(string $slug): void
    {
        $this->article = SupportArticle::with('author:id,name,username,role_id')
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $this->article->is_published
            && ! Gate::allows('update', $this->article)) {
            abort(404);
        }
    }
}; ?>

<div>
    <div class="relative w-full py-6 px-2">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('knowledge')" wire:navigate>Knowledge</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('knowledge.articles')" wire:navigate>Articles</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $article->title }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <article class="max-w-3xl mx-auto rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        @if ($article->featured_image)
            <img src="{{ Storage::disk('s3')->temporaryUrl($article->featured_image, now()->addMinutes(60)) }}" alt="{{ $article->title }}"
                class="w-full h-72 object-cover" />
        @endif

        <div class="p-8 space-y-5">
            <div class="flex items-center gap-2">
                @if ($article->category)
                    <flux:badge color="blue" size="sm">{{ $article->category }}</flux:badge>
                @endif
                @if (! $article->is_published)
                    <flux:badge color="zinc" size="sm">Draft</flux:badge>
                @endif
            </div>

            <flux:heading size="xl" level="1">{{ $article->title }}</flux:heading>

            <div class="flex items-center justify-between flex-wrap gap-3 pb-4 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <flux:avatar circle name="{{ $article->author->username ?? $article->author->name }}" color="auto"
                        color:seed="{{ $article->author->username ?? $article->author->name }}" size="sm" />
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $article->author?->name ?? 'Unknown' }}</p>
                        <p class="text-[11px] text-zinc-500">
                            {{ $article->published_at?->format('d M Y') ?? $article->created_at?->format('d M Y') }}
                            • {{ $article->read_time }} min read
                        </p>
                    </div>
                </div>

                @if (Gate::allows('update', $article) || Gate::allows('delete', $article))
                    <div class="flex gap-2">
                        @can('update', $article)
                            <flux:button size="sm" variant="ghost" icon="pencil"
                                :href="route('knowledge.articles-edit', $article->slug)" wire:navigate>Edit</flux:button>
                        @endcan
                    </div>
                @endif
            </div>

            @if ($article->excerpt)
                <p class="text-base text-zinc-600 dark:text-zinc-300 italic">{{ $article->excerpt }}</p>
            @endif

            <div class="prose prose-zinc dark:prose-invert max-w-none whitespace-pre-wrap leading-relaxed text-zinc-800 dark:text-zinc-200">{{ $article->content }}</div>
        </div>
    </article>
</div>
