<?php

use App\Models\User;
use App\Services\ProjectCache;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public int $id;
    public ?int $leaderId = null;
    public mixed $internal = [];
    public mixed $internals = [];
    public array $timduk = [];
    public string $search = '';

    public string $userSearch = '';
    public ?string $nameTimduk = null;

    public ?int $deletingInternalId = null;
    public ?string $deletingInternalName = null;

    public ?string $deletingTimdukName = null;

    public function placeholder()
    {
        return view('components.placeholder.ph_project_team_tabs');
    }

    public function mount(): void
    {
        $ids = collect($this->internal)->pluck('user_id');
        $this->internals = $this->internal;
        $this->internal = User::whereIn('id', $ids)->with('role')->get();
    }

    /**
     * Hanya pemilik project (project leader) atau pengelola dengan scope penuh
     * yang boleh mengubah anggota tim internal & tim pendukung. Anggota yang
     * hanya tergabung di tim internal tidak diizinkan.
     */
    public function canManage(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return (int) $user->id === (int) $this->leaderId
            || $user->viewScopeFor('project') === 'all';
    }

    public function getSearchResultsProperty(): array
    {
        $term = Str::lower(trim($this->search));

        if ($term === '') {
            return [
                'internal' => collect($this->internal)->values(),
                'timduk' => collect($this->timduk)->values(),
            ];
        }

        $internal = collect($this->internal)->filter(
            fn ($user) => Str::contains(Str::lower($user->name ?? ''), $term)
                || Str::contains(Str::lower($user->username ?? ''), $term)
                || Str::contains(Str::lower($user->email ?? ''), $term)
        )->values();

        $timduk = collect($this->timduk)->filter(
            fn ($name) => Str::contains(Str::lower($name), $term)
        )->values();

        return [
            'internal' => $internal,
            'timduk' => $timduk,
        ];
    }

    public function getAvailableUsersProperty()
    {
        $existingIds = collect($this->internal)->pluck('id')->all();
        $term = Str::lower(trim($this->userSearch));

        $query = User::query()
            ->with('role')
            ->whereNotIn('role_id', [1, 2])
            ->whereNotIn('id', $existingIds)
            ->orderBy('name');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"]);
            });
        }

        return $query->limit(50)->get();
    }

    public function inviteInternal(int $userId): void
    {
        if (! $this->canManage()) {
            Toaster::error('Hanya pemilik project yang dapat menambahkan anggota');
            return;
        }

        if (collect($this->internal)->pluck('id')->contains($userId)) {
            Toaster::error('Anggota sudah ditambahkan');
            return;
        }

        try {
            $response = Http::post(config('services.api_project').'project-teams', [
                'project_id' => $this->id,
                'user_id' => $userId,
            ]);

            if ($response->json('status') === 201) {
                $user = User::with('role')->find($response->json('data.user_id'));

                if ($user && ! $this->internal->contains('id', $user->id)) {
                    $this->internal->push($user);
                    $this->internals = collect($this->internals)
                        ->push($response->json('data'))
                        ->all();

                    app(ProjectCache::class)->flushUser($userId);

                    $this->dispatch('projectLoad');
                    Toaster::success('Anggota berhasil ditambahkan');
                    return;
                }
            }

            Toaster::error('Gagal menambahkan anggota');
        } catch (\Throwable $th) {
            Toaster::error('Gagal menambahkan anggota');
            Log::error('Failed to invite internal', ['error' => $th->getMessage()]);
        }
    }

    public function confirmDeleteInternal(int $id): void
    {
        if (! $this->canManage()) {
            Toaster::error('Hanya pemilik project yang dapat menghapus anggota');
            return;
        }

        $user = collect($this->internal)->firstWhere('id', $id);

        if (! $user) {
            Toaster::error('Anggota tidak ditemukan');
            return;
        }

        $this->deletingInternalId = $id;
        $this->deletingInternalName = $user['name'] ?? $user->name ?? '';
        Flux::modal('delete-internal-modal')->show();
    }

    public function removeInternal(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Hanya pemilik project yang dapat menghapus anggota');
            return;
        }

        if ($this->deletingInternalId === null) {
            return;
        }

        $id = $this->deletingInternalId;

        $teamId = collect($this->internals)
            ->firstWhere('user_id', $id)['id'] ?? null;

        if (! $teamId) {
            Toaster::error('Anggota tidak ditemukan');
            $this->reset('deletingInternalId', 'deletingInternalName');
            Flux::modal('delete-internal-modal')->close();
            return;
        }

        try {
            $response = Http::delete(config('services.api_project')."project-teams/{$teamId}");

            if ($response->json('status') === 200) {
                $this->internal = collect($this->internal)
                    ->reject(fn ($user) => $user->id === $id)
                    ->values();

                $this->internals = collect($this->internals)
                    ->reject(fn ($item) => (int) ($item['user_id'] ?? 0) === $id)
                    ->values()
                    ->all();

                app(ProjectCache::class)->flushUser($id);

                $this->dispatch('projectLoad');
                Toaster::success('Anggota berhasil dihapus');
            } else {
                Toaster::error('Gagal menghapus anggota');
            }
        } catch (\Throwable $th) {
            Toaster::error('Gagal menghapus anggota');
            Log::error('Failed to remove internal', ['error' => $th->getMessage()]);
        }

        $this->reset('deletingInternalId', 'deletingInternalName');
        Flux::modal('delete-internal-modal')->close();
    }

    public function addTimduk(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Hanya pemilik project yang dapat menambahkan tim pendukung');
            return;
        }

        if (! $this->nameTimduk || trim($this->nameTimduk) === '') {
            Toaster::error('Nama tim pendukung tidak boleh kosong');
            return;
        }

        $previous = $this->timduk;

        try {
            $this->timduk = collect($this->timduk)->push(trim($this->nameTimduk))->toArray();
            $this->nameTimduk = null;

            $response = Http::patch(config('services.api_project').'projects/'.$this->id, [
                'support_teams' => $this->timduk,
            ]);

            if ($response->json('status') === 200) {
                $this->dispatch('projectLoad');
                Toaster::success('Tim pendukung berhasil ditambahkan');
                return;
            }

            $this->timduk = $previous;
            Toaster::error('Gagal menambahkan tim pendukung');
        } catch (\Throwable $th) {
            $this->timduk = $previous;
            Toaster::error('Gagal menambahkan tim pendukung');
            Log::error('Failed to add timduk', ['error' => $th->getMessage()]);
        }
    }

    public function confirmDeleteTimduk(string $name): void
    {
        if (! $this->canManage()) {
            Toaster::error('Hanya pemilik project yang dapat menghapus tim pendukung');
            return;
        }

        if (! collect($this->timduk)->contains($name)) {
            Toaster::error('Tim pendukung tidak ditemukan');
            return;
        }

        $this->deletingTimdukName = $name;
        Flux::modal('delete-timduk-modal')->show();
    }

    public function removeTimduk(): void
    {
        if (! $this->canManage()) {
            Toaster::error('Hanya pemilik project yang dapat menghapus tim pendukung');
            return;
        }

        if ($this->deletingTimdukName === null) {
            return;
        }

        $name = $this->deletingTimdukName;
        $previous = $this->timduk;

        try {
            $this->timduk = collect($this->timduk)
                ->reject(fn ($n) => $n === $name)
                ->values()
                ->toArray();

            $response = Http::patch(config('services.api_project').'projects/'.$this->id, [
                'support_teams' => $this->timduk,
            ]);

            if ($response->json('status') === 200) {
                $this->dispatch('projectLoad');
                Toaster::success('Tim pendukung berhasil dihapus');
            } else {
                $this->timduk = $previous;
                Toaster::error('Gagal menghapus tim pendukung');
            }
        } catch (\Throwable $th) {
            $this->timduk = $previous;
            Toaster::error('Gagal menghapus tim pendukung');
            Log::error('Failed to remove timduk', ['error' => $th->getMessage()]);
        }

        $this->reset('deletingTimdukName');
        Flux::modal('delete-timduk-modal')->close();
    }
}; ?>

<div class="space-y-6">
    {{-- TOOLBAR --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="relative w-full sm:max-w-sm">
            <flux:input
                icon="magnifying-glass"
                placeholder="Cari nama, email, atau tim pendukung..."
                wire:model.live.debounce.300ms="search"
                clearable
            />
        </div>

        <div class="hidden sm:flex items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300 font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                {{ count($this->searchResults['internal']) }} Internal
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300 font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                {{ count($this->searchResults['timduk']) }} Pendukung
            </span>
        </div>
    </div>

    {{-- TIM INTERNAL --}}
    <section class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-linear-to-r from-blue-50/60 to-transparent dark:from-blue-500/5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-sm shadow-blue-500/20">
                    <flux:icon name="users" class="w-5 h-5 text-white" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Tim Internal</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ count($this->searchResults['internal']) }} anggota aktif
                    </p>
                </div>
            </div>
            @if ($this->canManage())
            <flux:modal.trigger name="invite-internal-modal">
                <flux:button size="sm" icon="user-plus" variant="primary">
                    <span class="hidden sm:inline">Tambah Anggota</span>
                    <span class="sm:hidden">Tambah</span>
                </flux:button>
            </flux:modal.trigger>
            @endif
        </header>

        <div class="p-5">
            @if (count($this->searchResults['internal']) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($this->searchResults['internal'] as $tim)
                        <article
                            wire:key="internal-{{ $tim['id'] }}"
                            class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/40 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/10 hover:border-blue-300 dark:hover:border-blue-500/40"
                        >
                            {{-- Decorative gradient blob --}}
                            <div class="pointer-events-none absolute -top-12 -right-12 w-32 h-32 rounded-full bg-linear-to-br from-blue-400/20 to-indigo-500/10 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                            {{-- Top accent bar --}}
                            <div class="h-1 w-full bg-linear-to-r from-blue-500 via-indigo-500 to-blue-500"></div>

                            <div class="relative p-4">
                                {{-- Action menu (top-right) --}}
                                @if ($this->canManage())
                                <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <flux:dropdown align="end">
                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" inset />
                                        <flux:menu>
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="confirmDeleteInternal({{ $tim['id'] }})"
                                            >
                                                Hapus Anggota
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                                @endif

                                {{-- Avatar with ring + status --}}
                                <div class="relative inline-flex">
                                    <div class="p-0.5 rounded-full bg-linear-to-br from-blue-400 to-indigo-500">
                                        <div class="p-0.5 rounded-full bg-white dark:bg-zinc-800">
                                            <flux:avatar
                                                circle
                                                name="{{ $tim['username'] ?? $tim['name'] }}"
                                                color="auto"
                                                color:seed="{{ $tim['username'] ?? $tim['name'] }}"
                                                size="lg"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {{-- Info --}}
                                <div class="mt-3 min-w-0">
                                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white truncate">
                                        {{ $tim['name'] }}
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate flex items-center gap-1">
                                        <flux:icon name="envelope" class="w-3 h-3 shrink-0" />
                                        {{ $tim['email'] }}
                                    </p>
                                </div>

                                {{-- Footer badges --}}
                                <div class="mt-4 pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700/60 flex items-center justify-between gap-2">
                                    @if (! empty($tim['role']['name']))
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 text-[11px] font-medium">
                                            <flux:icon name="shield-check" class="w-3 h-3" />
                                            {{ $tim['role']['name'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-700/50 text-zinc-600 dark:text-zinc-300 text-[11px] font-medium">
                                            No Role
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 px-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
                    <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center mb-3">
                        <flux:icon name="users" class="w-6 h-6 text-blue-500" />
                    </div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        @if (trim($this->search) !== '')
                            Tidak ada hasil untuk "{{ $this->search }}"
                        @else
                            Belum ada anggota tim internal
                        @endif
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        Tambahkan anggota tim untuk mulai berkolaborasi
                    </p>
                </div>
            @endif
        </div>
    </section>

    {{-- TIM PENDUKUNG --}}
    <section class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-linear-to-r from-amber-50/60 to-transparent dark:from-amber-500/5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-amber-500 to-orange-500 flex items-center justify-center shadow-sm shadow-amber-500/20">
                    <flux:icon name="briefcase" class="w-5 h-5 text-white" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Tim Pendukung</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ count($this->searchResults['timduk']) }} tim aktif
                    </p>
                </div>
            </div>
            @if ($this->canManage())
            <flux:modal.trigger name="invite-ppk-modal">
                <flux:button size="sm" icon="plus" variant="primary">
                    <span class="hidden sm:inline">Tambah Timduk</span>
                    <span class="sm:hidden">Tambah</span>
                </flux:button>
            </flux:modal.trigger>
            @endif
        </header>

        <div class="p-5">
            @if (count($this->searchResults['timduk']) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($this->searchResults['timduk'] as $tim)
                        <article
                            wire:key="timduk-{{ md5($tim) }}"
                            class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/40 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-500/10 hover:border-amber-300 dark:hover:border-amber-500/40"
                        >
                            <div class="pointer-events-none absolute -top-12 -right-12 w-32 h-32 rounded-full bg-linear-to-br from-amber-400/20 to-orange-500/10 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                            <div class="h-1 w-full bg-linear-to-r from-amber-500 via-orange-500 to-amber-500"></div>

                            <div class="relative p-4">
                                @if ($this->canManage())
                                <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <flux:dropdown align="end">
                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" inset />
                                        <flux:menu>
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="confirmDeleteTimduk('{{ addslashes($tim) }}')"
                                            >
                                                Hapus Tim
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                                @endif

                                <div class="relative inline-flex">
                                    <div class="p-0.5 rounded-full bg-linear-to-br from-amber-400 to-orange-500">
                                        <div class="p-0.5 rounded-full bg-white dark:bg-zinc-800">
                                            <flux:avatar
                                                circle
                                                name="{{ $tim }}"
                                                color="auto"
                                                color:seed="{{ $tim }}"
                                                size="lg"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 min-w-0">
                                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white truncate">
                                        {{ $tim }}
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate flex items-center gap-1">
                                        <flux:icon name="building-office" class="w-3 h-3 shrink-0" />
                                        Tim Pendukung
                                    </p>
                                </div>

                                <div class="mt-4 pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700/60 flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 text-[11px] font-medium">
                                        <flux:icon name="briefcase" class="w-3 h-3" />
                                        PPK
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Kejaksaan Agung
                                    </span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 px-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
                    <div class="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center mb-3">
                        <flux:icon name="briefcase" class="w-6 h-6 text-amber-500" />
                    </div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        @if (trim($this->search) !== '')
                            Tidak ada hasil untuk "{{ $this->search }}"
                        @else
                            Belum ada tim pendukung
                        @endif
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        Tambahkan tim pendukung untuk project ini
                    </p>
                </div>
            @endif
        </div>
    </section>

    {{-- MODAL: INVITE INTERNAL (searchable list, no select2) --}}
    <flux:modal name="invite-internal-modal" class="w-md" @close="$wire.set('userSearch', '')">
        <div class="space-y-5">
            {{-- Header --}}
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-sm shadow-blue-500/20">
                    <flux:icon name="user-plus" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <flux:heading size="lg">Tambah Anggota Internal</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        Klik nama untuk langsung menambahkan ke project
                    </flux:text>
                </div>
            </div>

            {{-- Search --}}
            <flux:input
                icon="magnifying-glass"
                placeholder="Cari nama, username, atau email..."
                wire:model.live.debounce.250ms="userSearch"
                clearable
            />

            {{-- Available users list --}}
            <div>
                <div class="flex items-center justify-between mb-2 px-1">
                    <flux:text class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">
                        Pengguna Tersedia
                    </flux:text>
                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                        {{ count($this->availableUsers) }} pengguna
                    </span>
                </div>

                <div class="space-y-1 max-h-72 overflow-y-auto pr-1 -mr-1" wire:loading.class="opacity-60">
                    @forelse ($this->availableUsers as $user)
                        <button
                            type="button"
                            wire:key="available-user-{{ $user->id }}"
                            wire:click="inviteInternal({{ $user->id }})"
                            wire:loading.attr="disabled"
                            class="w-full flex items-center gap-3 p-2.5 rounded-xl border border-transparent hover:border-blue-200 dark:hover:border-blue-500/30 hover:bg-blue-50/50 dark:hover:bg-blue-500/5 transition group text-left disabled:opacity-50"
                        >
                            <flux:avatar
                                circle
                                name="{{ $user->username ?? $user->name }}"
                                color="auto"
                                color:seed="{{ $user->username ?? $user->name }}"
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">
                                    {{ $user->name }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate flex items-center gap-1.5">
                                    @if ($user->role?->name)
                                        <span class="inline-flex items-center px-1.5 py-0 rounded bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 text-[10px] font-medium">
                                            {{ $user->role->name }}
                                        </span>
                                    @endif
                                    <span class="truncate">{{ $user->email }}</span>
                                </p>
                            </div>
                            <span class="shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-lg bg-zinc-100 dark:bg-zinc-700/50 text-zinc-400 group-hover:bg-blue-500 group-hover:text-white transition">
                                <flux:icon name="plus" class="w-4 h-4" />
                            </span>
                        </button>
                    @empty
                        <div class="flex flex-col items-center justify-center py-10 px-4 text-center">
                            <div class="w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-2">
                                <flux:icon name="magnifying-glass" class="w-5 h-5 text-zinc-400" />
                            </div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                @if (trim($this->userSearch) !== '')
                                    Tidak ditemukan
                                @else
                                    Semua pengguna sudah ditambahkan
                                @endif
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                @if (trim($this->userSearch) !== '')
                                    Coba kata kunci lain
                                @else
                                    Tidak ada lagi pengguna untuk ditambahkan
                                @endif
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Current members (collapsible-like) --}}
            @if (count($this->internal) > 0)
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    <div class="flex items-center justify-between mb-2 px-1">
                        <flux:text class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">
                            Sudah Ditambahkan
                        </flux:text>
                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                            {{ count($this->internal) }} anggota
                        </span>
                    </div>
                    <div class="space-y-1 max-h-40 overflow-y-auto pr-1 -mr-1">
                        @foreach ($this->internal as $tim)
                            <div
                                wire:key="modal-internal-{{ $tim['id'] }}"
                                class="flex items-center gap-3 p-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition"
                            >
                                <flux:avatar
                                    circle
                                    name="{{ $tim['username'] ?? $tim['name'] }}"
                                    color="auto"
                                    color:seed="{{ $tim['username'] ?? $tim['name'] }}"
                                    size="xs"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-zinc-900 dark:text-white truncate">
                                        {{ $tim['name'] }}
                                    </p>
                                </div>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="confirmDeleteInternal({{ $tim['id'] }})"
                                />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- MODAL: INVITE PPK --}}
    <flux:modal name="invite-ppk-modal" class="w-md">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-linear-to-br from-amber-500 to-orange-500 flex items-center justify-center shadow-sm shadow-amber-500/20">
                    <flux:icon name="briefcase" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <flux:heading size="lg">Tambah Tim Pendukung</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        Tambahkan tim pendukung lainnya
                    </flux:text>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:input
                    wire:model.live="nameTimduk"
                    placeholder="Nama tim pendukung..."
                    class="flex-1"
                    wire:keydown.enter="addTimduk"
                    icon="user"
                />
                <flux:button wire:click="addTimduk" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="addTimduk">
                    <span wire:loading.remove wire:target="addTimduk">Tambah</span>
                    <span wire:loading wire:target="addTimduk">Menambah...</span>
                </flux:button>
            </div>

            <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <div class="flex items-center justify-between mb-2 px-1">
                    <flux:text class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">
                        Tim Pendukung Saat Ini
                    </flux:text>
                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                        {{ count($this->timduk) }} tim
                    </span>
                </div>

                <div class="space-y-1 max-h-64 overflow-y-auto pr-1 -mr-1">
                    @forelse ($this->timduk as $tim)
                        <div
                            wire:key="modal-timduk-{{ md5($tim) }}"
                            class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition"
                        >
                            <flux:avatar
                                circle
                                name="{{ $tim }}"
                                color="auto"
                                color:seed="{{ $tim }}"
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">
                                    {{ $tim }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">
                                    Tim Pendukung
                                </p>
                            </div>
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="trash"
                                wire:click="confirmDeleteTimduk('{{ addslashes($tim) }}')"
                            />
                        </div>
                    @empty
                        <div class="text-center text-sm text-zinc-400 py-6">
                            Belum ada tim pendukung
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- MODAL: CONFIRM DELETE INTERNAL --}}
    <flux:modal name="delete-internal-modal" class="md:w-110">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center ring-4 ring-red-50/50 dark:ring-red-500/5">
                    <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600 dark:text-red-400" />
                </div>
                <div class="space-y-1 flex-1 min-w-0">
                    <flux:heading size="lg">Hapus Anggota Internal?</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        Anggota <span class="font-medium text-zinc-800 dark:text-zinc-200">"{{ $deletingInternalName }}"</span>
                        akan dikeluarkan dari project ini. Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="flex-1">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="removeInternal" variant="danger" icon="trash" class="flex-1"
                    wire:loading.attr="disabled" wire:target="removeInternal">
                    <span wire:loading.remove wire:target="removeInternal">Hapus</span>
                    <span wire:loading wire:target="removeInternal">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- MODAL: CONFIRM DELETE TIMDUK --}}
    <flux:modal name="delete-timduk-modal" class="md:w-110">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center ring-4 ring-red-50/50 dark:ring-red-500/5">
                    <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600 dark:text-red-400" />
                </div>
                <div class="space-y-1 flex-1 min-w-0">
                    <flux:heading size="lg">Hapus Tim Pendukung?</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        Tim <span class="font-medium text-zinc-800 dark:text-zinc-200">"{{ $deletingTimdukName }}"</span>
                        akan dihapus dari daftar tim pendukung project. Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="flex-1">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="removeTimduk" variant="danger" icon="trash" class="flex-1"
                    wire:loading.attr="disabled" wire:target="removeTimduk">
                    <span wire:loading.remove wire:target="removeTimduk">Hapus</span>
                    <span wire:loading wire:target="removeTimduk">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
