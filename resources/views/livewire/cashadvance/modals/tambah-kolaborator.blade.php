<?php

use App\Models\User;
use App\Services\CaWriter;
use Illuminate\Support\Str;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    /** @var array<string, mixed> */
    #[Reactive]
    public array $wallet = [];

    public string $userSearch = '';

    /**
     * Kolaborator saat ini; nama di-lookup ke DB lokal (payload API cuma
     * menyertakan username), dipakai untuk daftar "Sudah Ditambahkan" &
     * exclude dari daftar pencarian.
     *
     * @return array<int, array{collab_id: int, user_id: int, nama: string, username: string}>
     */
    public function currentCollaborators(): array
    {
        $raw = collect($this->wallet['collaborators'] ?? []);

        $users = User::whereIn('id', $raw->pluck('user_id'))->get()->keyBy('id');

        return $raw->map(fn (array $c): array => [
            'collab_id' => $c['id'],
            'user_id' => (int) $c['user_id'],
            'nama' => $users->get((int) $c['user_id'])->name ?? $c['username'],
            'username' => $c['username'],
        ])->values()->all();
    }

    /**
     * User lokal yang belum jadi kolaborator dompet ini, difilter pencarian.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function availableUsers(): \Illuminate\Support\Collection
    {
        $existingIds = collect($this->currentCollaborators())->pluck('user_id')->all();
        $term = Str::lower(trim($this->userSearch));

        $query = User::whereNotIn('role_id', [1, 2])
            ->whereNotIn('id', $existingIds)
            ->orderBy('name');

        if ($term !== '') {
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$term}%"]);
            });
        }

        return $query->limit(50)->get();
    }

    /**
     * Cuma pemilik dompet (atau role scope cashadvance 'all') yang boleh
     * menambah kolaborator baru.
     */
    private function canManageWallet(): bool
    {
        return (int) ($this->wallet['user_id'] ?? 0) === (int) Auth::id()
            || Auth::user()->viewScopeFor('cashadvance') === 'all';
    }

    public function invite(int $userId): void
    {
        if (! $this->canManageWallet()) {
            Toaster::error('Hanya pemilik dompet yang dapat menambah kolaborator.');

            return;
        }

        $user = User::findOrFail($userId);

        $result = app(CaWriter::class)->addCollaborator([
            'dompet_id' => $this->wallet['id'],
            'user_id' => $user->id,
            'username' => $user->username,
        ]);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menambah kolaborator.'));

            return;
        }

        Toaster::success('Kolaborator berhasil ditambahkan.');
        $this->dispatch('ca-updated');
    }
}; ?>

<flux:modal name="tambah-kolaborator" class="w-full max-w-md max-sm:max-h-[85dvh] overflow-auto" @close="$wire.set('userSearch', '')">
    <div class="space-y-5">
        {{-- Header --}}
        <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-ca-copper to-ca-copper-deep shadow-sm shadow-ca-copper/20">
                <flux:icon name="user-plus" class="size-5 text-white" />
            </div>
            <div>
                <flux:heading size="lg">Tambah Kolaborator</flux:heading>
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    Klik nama untuk langsung menambahkan ke dompet ini
                </flux:text>
            </div>
        </div>

        {{-- Search --}}
        <flux:input
            icon="magnifying-glass"
            placeholder="Cari nama atau username..."
            wire:model.live.debounce.250ms="userSearch"
            clearable />

        {{-- Available users --}}
        <div>
            <div class="mb-2 flex items-center justify-between px-1">
                <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Pengguna Tersedia
                </flux:text>
                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                    {{ $this->availableUsers()->count() }} pengguna
                </span>
            </div>

            <div class="-mr-1 max-h-72 space-y-1 overflow-y-auto pr-1" wire:loading.class="opacity-60" wire:target="userSearch">
                @forelse ($this->availableUsers() as $user)
                <button type="button" wire:key="available-user-{{ $user->id }}" wire:click="invite({{ $user->id }})"
                    wire:loading.attr="disabled" wire:target="invite({{ $user->id }})"
                    class="group flex w-full items-center gap-3 rounded-xl border border-transparent p-2.5 text-left transition hover:border-ca-copper/30 hover:bg-ca-copper/5 disabled:opacity-50">
                    <flux:avatar circle name="{{ $user->username ?? $user->name }}" color="auto" color:seed="{{ $user->username ?? $user->name }}" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $user->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ '@'.$user->username }}</p>
                    </div>
                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-400 transition group-hover:bg-ca-copper group-hover:text-white dark:bg-zinc-700/50">
                        <flux:icon name="plus" class="size-4" wire:loading.remove wire:target="invite({{ $user->id }})" />
                        <flux:icon name="arrow-path" class="size-4 animate-spin" wire:loading wire:target="invite({{ $user->id }})" />
                    </span>
                </button>
                @empty
                <div class="flex flex-col items-center justify-center px-4 py-10 text-center">
                    <div class="mb-2 flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="magnifying-glass" class="size-5 text-zinc-400" />
                    </div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        @if (trim($userSearch) !== '')
                        Tidak ditemukan
                        @else
                        Semua pengguna sudah ditambahkan
                        @endif
                    </p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                        @if (trim($userSearch) !== '')
                        Coba kata kunci lain
                        @else
                        Tidak ada lagi pengguna untuk ditambahkan
                        @endif
                    </p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Current collaborators --}}
        @php $current = $this->currentCollaborators(); @endphp
        @if (count($current) > 0)
        <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <div class="mb-2 flex items-center justify-between px-1">
                <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Sudah Ditambahkan
                </flux:text>
                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                    {{ count($current) }} kolaborator
                </span>
            </div>
            <div class="-mr-1 max-h-40 space-y-1 overflow-y-auto pr-1">
                @foreach ($current as $person)
                <div wire:key="modal-collab-{{ $person['collab_id'] }}" class="flex items-center gap-3 rounded-lg p-2 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                    <flux:avatar circle name="{{ $person['username'] }}" color="auto" color:seed="{{ $person['username'] }}" size="xs" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-medium text-zinc-900 dark:text-white">{{ $person['nama'] }}</p>
                    </div>
                    <flux:button size="xs" variant="ghost" icon="trash" type="button"
                        x-on:click="$wire.dispatch('ca-confirm-delete-collaborator', { id: {{ $person['collab_id'] }}, name: {!! \Illuminate\Support\Js::from($person['nama']) !!}, ownerId: {{ (int) ($wallet['user_id'] ?? 0) }} }); $flux.modal('hapus-kolaborator').show()" />
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</flux:modal>
