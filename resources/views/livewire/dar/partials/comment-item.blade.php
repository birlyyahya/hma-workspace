@php
    /**
     * Comment item (chat-style bubble).
     *
     * @var array $c Comment data ['id','user_id','body','created_at','files']
     * @var array|null $cu ['name','role_name'] from $commentUsers map
     * @var bool $isOwn Whether current user authored this comment
     * @var bool $isEditing Whether this comment is currently being edited
     */
    $c = $c ?? [];
    $cu = $cu ?? null;
    $isOwn = $isOwn ?? false;
    $isEditing = $isEditing ?? false;
    $name = $cu['name'] ?? 'Unknown';
    $roleName = $cu['role_name'] ?? null;
    $createdAt = ! empty($c['created_at']) ? \Carbon\Carbon::parse($c['created_at']) : null;
    $files = $c['files'] ?? [];
    $images = collect($files)->filter(fn ($f) => isImageFile($f['filename'] ?? '', $f['type'] ?? null))->values();
    $docs = collect($files)->filter(fn ($f) => ! isImageFile($f['filename'] ?? '', $f['type'] ?? null))->values();
@endphp

<article wire:key="comment-{{ $c['id'] }}" class="group flex gap-3 px-5 py-4 transition hover:bg-zinc-50/50">
    <flux:avatar circle name="{{ $name }}" size="sm" class="shrink-0" />

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span class="text-sm font-semibold text-zinc-900">{{ $name }}</span>
            @if ($roleName)
                <span class="text-xs text-zinc-500">· {{ $roleName }}</span>
            @endif
            @if ($createdAt)
                <span class="text-xs text-zinc-400" title="{{ $createdAt->format('d M Y, H:i') }}">
                    · {{ $createdAt->diffForHumans() }}
                </span>
            @endif
        </div>

        @if ($isEditing)
            <div class="mt-2 space-y-2">
                <textarea
                    wire:model="editingCommentBody"
                    rows="3"
                    class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-800 placeholder:text-zinc-400 focus:border-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-200"
                    placeholder="Edit your comment..."
                ></textarea>
                @error('editingCommentBody')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
                <div class="flex items-center gap-2">
                    <button
                        wire:click="updateComment"
                        wire:loading.attr="disabled"
                        wire:target="updateComment"
                        type="button"
                        class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-zinc-800 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="updateComment">Save</span>
                        <span wire:loading wire:target="updateComment" class="animate-pulse">Saving...</span>
                    </button>
                    <button
                        wire:click="cancelEditingComment"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-zinc-600 ring-1 ring-zinc-200 hover:bg-zinc-50"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        @else
            <div class="mt-1.5 inline-block max-w-full rounded-2xl rounded-tl-md bg-zinc-50 px-4 py-2.5 ring-1 ring-zinc-200/60">
                <p class="whitespace-pre-wrap break-words text-sm leading-relaxed text-zinc-800">{{ $c['body'] ?? '' }}</p>
            </div>

            @if ($images->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($images as $file)
                        @include('livewire.dar.partials.attachment-card', ['file' => $file, 'variant' => 'thumb'])
                    @endforeach
                </div>
            @endif

            @if ($docs->isNotEmpty())
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($docs as $file)
                        @include('livewire.dar.partials.attachment-card', ['file' => $file, 'variant' => 'card'])
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    @if (! $isEditing && $isOwn)
        <div x-data="{ open: false }" class="relative shrink-0 opacity-0 transition group-hover:opacity-100">
            <button
                type="button"
                @click="open = !open"
                class="grid h-8 w-8 place-items-center rounded-full text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700"
                aria-label="Comment actions"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                    <circle cx="5" cy="12" r="1.6" />
                    <circle cx="12" cy="12" r="1.6" />
                    <circle cx="19" cy="12" r="1.6" />
                </svg>
            </button>
            <div
                x-cloak
                x-show="open"
                @click.away="open = false"
                x-transition.origin.top.right
                class="absolute right-0 z-20 mt-1 w-36 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-200/70"
            >
                <button
                    wire:click="startEditingComment({{ $c['id'] }}, @js($c['body'] ?? ''))"
                    @click="open = false"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50"
                >
                    <flux:icon name="pencil-square" class="h-4 w-4" />
                    Edit
                </button>
                <button
                    @click.prevent="open = false; if (confirm('Hapus komentar ini?')) $wire.deleteComment({{ $c['id'] }})"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
                >
                    <flux:icon name="trash" class="h-4 w-4" />
                    <span wire:loading.remove wire:target="deleteComment({{ $c['id'] }})">Delete</span>
                    <span wire:loading wire:target="deleteComment({{ $c['id'] }})" class="animate-pulse">Deleting...</span>
                </button>
            </div>
        </div>
    @endif
</article>
