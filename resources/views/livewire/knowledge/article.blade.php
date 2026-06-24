<?php

use App\Models\SupportArticle;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $category = 'all';
    public string $scope = 'published'; // published|mine|all-drafts (admin)

    public ?int $deletingId = null;
    public ?string $deletingTitle = null;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'category', 'scope'])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function categories(): array
    {
        return SupportArticle::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function items()
    {
        $userId = auth()->id();
        $level = (int) (auth()->user()->level ?? 0);

        return SupportArticle::query()
            ->with('author:id,name,username')
            ->when(
                $this->scope === 'mine',
                fn ($q) => $q->where('user_id', $userId),
                fn ($q) => $q->when(
                    $this->scope === 'published' || $level < 90,
                    fn ($q) => $q->published()
                ),
            )
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->when($this->search, fn ($q) => $q->where(function ($qq) {
                $like = '%'.Str::lower($this->search).'%';
                $qq->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(excerpt) LIKE ?', [$like]);
            }))
            ->latest('published_at')
            ->latest()
            ->paginate(9);
    }

    public function confirmDelete(int $id): void
    {
        $item = SupportArticle::find($id);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }
        $this->deletingId = $id;
        $this->deletingTitle = $item->title;
        Flux::modal('article-delete-modal')->show();
    }

    public function delete(): void
    {
        $item = SupportArticle::find($this->deletingId);
        if (! $item || Gate::denies('delete', $item)) {
            Toaster::error('Anda tidak memiliki akses');
            return;
        }

        if ($item->featured_image) {
            Storage::disk('s3')->delete($item->featured_image);
        }

        $item->delete();
        Toaster::success('Artikel dihapus');

        $this->reset('deletingId', 'deletingTitle');
        Flux::modal('article-delete-modal')->close();
    }
}; ?>

<div>
    <div class="relative mb-6 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-2">{{ __('Support Center') }}</flux:heading>
        <flux:subheading>{{ __('Artikel & insight dari user') }}</flux:subheading>
        <flux:separator variant="subtle" class="mt-4" />
    </div>

    <x-settings.knowledge-layout
        :heading="__('Articles')"
        :subheading="__('Browse articles and insights shared by users.')"
    >
        <x-slot name="action">
            @can('create', App\Models\SupportArticle::class)
                <flux:button icon="pencil-square" variant="primary" :href="route('knowledge.articles-create')" wire:navigate>
                    Tulis Artikel
                </flux:button>
            @endcan
        </x-slot>

        <div class="flex flex-col sm:flex-row gap-3 mb-5">
            <flux:input
                icon="magnifying-glass"
                placeholder="Cari artikel..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="flex-1"
            />
            <flux:select wire:model.live="category" class="sm:w-48">
                <option value="all">Semua Kategori</option>
                @foreach ($this->categories as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="scope" class="sm:w-48">
                <option value="published">Published</option>
                <option value="mine">Artikel Saya</option>
            </flux:select>
        </div>

        @php($articles = $this->items())

        @if ($articles->count() === 0)
            <div class="flex flex-col items-center justify-center py-16 px-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center mb-3">
                    <flux:icon name="newspaper" class="w-6 h-6 text-blue-500" />
                </div>
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Belum ada artikel</p>
                <p class="text-xs text-zinc-500 mt-1">Mulai berbagi insight dengan menulis artikel pertama</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($articles as $article)
                    <article wire:key="art-{{ $article->id }}"
                        class="group rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden hover:shadow-md transition flex flex-col">
                        <a href="{{ route('knowledge.articles-show', $article->slug) }}" wire:navigate>
                            @if ($article->featured_image)
                                <img src="{{ Storage::url($article->featured_image) }}" alt="{{ $article->title }}"
                                    class="w-full h-40 object-cover" />
                            @else
                                <div class="w-full h-40 bg-linear-to-br from-blue-100 to-indigo-100 dark:from-blue-500/10 dark:to-indigo-500/10 flex items-center justify-center">
                                    <flux:icon name="newspaper" class="w-10 h-10 text-blue-400" />
                                </div>
                            @endif
                        </a>

                        <div class="p-4 flex-1 flex flex-col">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                @if ($article->category)
                                    <flux:badge color="blue" size="sm">{{ $article->category }}</flux:badge>
                                @else
                                    <span></span>
                                @endif

                                @if (! $article->is_published)
                                    <flux:badge color="zinc" size="sm">Draft</flux:badge>
                                @endif
                            </div>

                            <a href="{{ route('knowledge.articles-show', $article->slug) }}" wire:navigate
                                class="text-sm font-semibold text-zinc-900 dark:text-white line-clamp-2 group-hover:text-blue-600">
                                {{ $article->title }}
                            </a>

                            @if ($article->excerpt)
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 mt-1.5 flex-1">
                                    {{ $article->excerpt }}
                                </p>
                            @endif

                            <div class="flex items-center justify-between text-[11px] text-zinc-400 mt-3 pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700/60">
                                <span class="truncate">{{ $article->author?->name ?? 'Unknown' }}</span>
                                <span>{{ $article->read_time }} min • {{ $article->created_at?->diffForHumans() }}</span>
                            </div>

                            @if (Gate::allows('update', $article) || Gate::allows('delete', $article))
                                <div class="flex gap-1 mt-2 -mb-1">
                                    @can('update', $article)
                                        <flux:button size="xs" variant="ghost" icon="pencil"
                                            :href="route('knowledge.articles-edit', $article->slug)" wire:navigate>Edit</flux:button>
                                    @endcan
                                    @can('delete', $article)
                                        <flux:button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDelete({{ $article->id }})">Hapus</flux:button>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $articles->links() }}
            </div>
        @endif
    </x-settings.knowledge-layout>

    {{-- DELETE MODAL --}}
    <flux:modal name="article-delete-modal" class="md:w-110">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
                    <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600" />
                </div>
                <div class="space-y-1 flex-1">
                    <flux:heading size="lg">Hapus Artikel?</flux:heading>
                    <flux:text class="text-sm text-zinc-500">
                        "<span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $deletingTitle }}</span>" akan dihapus permanen.
                    </flux:text>
                </div>
            </div>
            <div class="flex gap-2">
                <flux:modal.close><flux:button variant="ghost" class="flex-1">Batal</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash" class="flex-1"
                    wire:loading.attr="disabled" wire:target="delete">
                    <span wire:loading.remove wire:target="delete">Hapus</span>
                    <span wire:loading wire:target="delete">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
