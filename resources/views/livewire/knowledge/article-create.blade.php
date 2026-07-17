<?php

use App\Models\SupportArticle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app')]
class extends Component {
    use WithFileUploads;

    public ?SupportArticle $article = null;

    public string $title = '';
    public string $excerpt = '';
    public string $content = '';
    public string $category = '';
    public bool $isPublished = false;

    public $image = null;
    public ?string $existingImage = null;

    public function mount(?string $slug = null): void
    {
        if (! Gate::allows('create', SupportArticle::class)) {
            abort(403);
        }

        if ($slug) {
            $this->article = SupportArticle::where('slug', $slug)->firstOrFail();

            if (Gate::denies('update', $this->article)) {
                abort(403);
            }

            $this->title = $this->article->title;
            $this->excerpt = $this->article->excerpt ?? '';
            $this->content = $this->article->content;
            $this->category = $this->article->category ?? '';
            $this->isPublished = (bool) $this->article->is_published;
            $this->existingImage = $this->article->featured_image;
        }
    }

    public function save(bool $publish = false): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $payload = [
            'title' => $data['title'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'category' => $data['category'] ?: null,
            'is_published' => $publish || $this->isPublished,
        ];

        if ($this->image) {
            if ($this->existingImage) {
                Storage::disk('s3')
                ->delete($this->existingImage);
            }
            $payload['featured_image'] = $this->image->store('knowledge/article', config('filesystems.default'));
        }

        if ($this->article) {
            $this->article->update($payload);
            Toaster::success('Artikel diperbarui');
        } else {
            $payload['user_id'] = Auth::id();
            $payload['created_by'] = Auth::id();
            $this->article = SupportArticle::create($payload);
            Toaster::success('Artikel disimpan');
        }

        $this->redirect(route('knowledge.articles-show', $this->article->slug), navigate: true);
    }

    public function publish(): void
    {
        $this->save(publish: true);
    }
}; ?>

<div>
    <div class="relative w-full py-6 px-2">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('knowledge')" wire:navigate>Knowledge</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('knowledge.articles')" wire:navigate>Articles</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $article ? 'Edit' : 'Create new' }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- FORM --}}
        <div class="p-6 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 space-y-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                    <flux:icon name="pencil-square" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $article ? 'Edit Artikel' : 'Tulis Artikel Baru' }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">Slug akan dibuat otomatis dari judul</flux:text>
                </div>
            </div>

            <flux:input wire:model.live.debounce.500ms="title" label="Judul" placeholder="Tulis judul yang menarik..." />

            <flux:input wire:model="category" label="Kategori (opsional)" placeholder="Tips, Tutorial, Workflow, ..." />

            <flux:textarea wire:model="excerpt" label="Ringkasan (opsional)" rows="2" placeholder="Ringkasan singkat untuk preview kartu..." />

            <flux:textarea wire:model.live.debounce.500ms="content" label="Konten" rows="14" placeholder="Tulis isi artikel di sini..." />

            <div>
                <flux:text class="text-sm font-medium mb-2 block">Featured Image (max 5MB)</flux:text>
                <label for="dropzone-file"
                    class="flex items-center justify-center w-full h-48 rounded-xl border-2 border-dashed cursor-pointer overflow-hidden
                    {{ $image || $existingImage ? 'border-zinc-200' : 'border-zinc-300 hover:bg-zinc-50' }} dark:border-zinc-700">
                    @if ($image)
                        <img src="{{ $image->temporaryUrl() }}" class="w-full h-full object-cover" />
                    @elseif ($existingImage)
                        <img src="{{ Storage::disk('s3')->temporaryUrl($existingImage, now()->addMinutes(60)) }}" class="w-full h-full object-cover" />
                    @else
                        <div class="flex flex-col items-center text-zinc-400">
                            <flux:icon.photo class="w-8 h-8 mb-2" />
                            <p class="text-xs">Klik untuk pilih gambar</p>
                        </div>
                    @endif
                    <input id="dropzone-file" wire:model="image" type="file" accept="image/*" class="hidden" />
                </label>
                @error('image') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:switch wire:model="isPublished" label="Publish setelah simpan" />

                <div class="flex gap-2">
                    <flux:button variant="ghost" :href="route('knowledge.articles')" wire:navigate>Batal</flux:button>
                    <flux:button wire:click="save" variant="filled" icon="document"
                        wire:loading.attr="disabled" wire:target="save,publish,image">
                        <span wire:loading.remove wire:target="save,publish">Simpan Draft</span>
                        <span wire:loading wire:target="save,publish">Menyimpan...</span>
                    </flux:button>
                    <flux:button wire:click="publish" variant="primary" icon="paper-airplane"
                        wire:loading.attr="disabled" wire:target="save,publish,image">
                        Publish
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- PREVIEW --}}
        <div class="p-6 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 space-y-4">
            <div class="flex items-center gap-2 text-xs text-zinc-400 uppercase tracking-wide">
                <flux:icon name="eye" class="w-4 h-4" /> Live Preview
            </div>

            @if ($image || $existingImage)
                <img src="{{ $image ? $image->temporaryUrl() : Storage::disk('s3')->temporaryUrl($existingImage, now()->addMinutes(60)) }}"
                    class="w-full h-48 object-cover rounded-xl" />
            @endif

            @if ($category)
                <flux:badge color="blue" size="sm">{{ $category }}</flux:badge>
            @endif

            <flux:heading size="xl" level="1">{{ $title ?: 'Judul artikel akan muncul di sini' }}</flux:heading>

            @if ($excerpt)
                <p class="text-sm text-zinc-500 italic">{{ $excerpt }}</p>
            @endif

            <flux:separator variant="subtle" />

            <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap text-zinc-700 dark:text-zinc-300">
                {{ $content ?: 'Konten preview akan muncul di sini saat Anda mengetik...' }}
            </div>
        </div>
    </div>
</div>
