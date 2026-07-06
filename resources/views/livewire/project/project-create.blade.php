<?php

use App\Models\User;
use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component
{
    public $name             = null;
    public $code             = null;
    public $contract_number  = null;
    public $contract_date    = null;
    public $client           = null;
    public $ppk              = null;
    public $value            = null;
    public $status           = 'WAITING';
    public $start_date       = null;
    public $end_date         = null;
    public $maintenance_date = null;
    public $project_leader_id = null;
    public $company_id       = null;

    public array $support_teams = [];
    public $newSupportTeam = '';

    public bool $submitting = false;

    public function mount(): void
    {
        $this->authorize('project.create');
    }

    public function getCompaniesProperty(): array
    {
        return app(ProjectCache::class)->allCompanies();
    }

    public function getUsersProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereNotIn('role_id', [1, 2])->orderBy('name')->get();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function getCompanyOptionsProperty(): array
    {
        return collect($this->companies)
            ->map(fn ($c) => ['value' => (int) $c['id'], 'label' => (string) $c['name']])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function getLeaderOptionsProperty(): array
    {
        return $this->users
            ->reject(fn ($user) => in_array($user->role_id, [
                '1',
                '10',
                '11',
            ]))
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
            ->all();
    }

    protected function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'min:5'],
            'code'              => ['required', 'string'],
            'contract_number'   => ['required', 'string'],
            'contract_date'     => ['required', 'date'],
            'client'            => ['required', 'string'],
            'ppk'               => ['required', 'string'],
            'value'             => ['required', 'numeric', 'min:0'],
            'status'            => ['required', 'string', 'in:WAITING,ON PROGRESS,CLOSED'],
            'start_date'        => ['required', 'date'],
            'end_date'          => ['required', 'date', 'after_or_equal:start_date'],
            'maintenance_date'  => ['nullable', 'date', 'after_or_equal:end_date'],
            'project_leader_id' => ['required', 'integer'],
            'company_id'        => ['required', 'integer'],
            'support_teams'     => ['array', 'nullable'],
            'support_teams.*'   => ['string', 'min:2'],
        ];
    }

    public function addSupportTeam(): void
    {
        $name = trim($this->newSupportTeam);

        if ($name === '') {
            return;
        }

        if (in_array($name, $this->support_teams, true)) {
            Toaster::error('Tim pendukung sudah ditambahkan');
            return;
        }

        $this->support_teams[] = $name;
        $this->newSupportTeam = '';
    }

    public function removeSupportTeam(int $index): void
    {
        unset($this->support_teams[$index]);
        $this->support_teams = array_values($this->support_teams);
    }

    protected function messages(): array
    {
        return [
            'name.required'                   => 'Nama proyek wajib diisi.',
            'name.min'                        => 'Nama proyek minimal 5 karakter.',
            'code.required'                   => 'Kode proyek wajib diisi.',
            'contract_number.required'        => 'Nomor kontrak wajib diisi.',
            'contract_date.required'          => 'Tanggal kontrak wajib diisi.',
            'client.required'                 => 'Nama klien wajib diisi.',
            'ppk.required'                    => 'Nama PPK wajib diisi.',
            'value.required'                  => 'Nilai kontrak wajib diisi.',
            'value.numeric'                   => 'Nilai kontrak harus berupa angka.',
            'status.required'                 => 'Status wajib dipilih.',
            'start_date.required'             => 'Tanggal mulai wajib diisi.',
            'end_date.required'               => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal'         => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
            'maintenance_date.after_or_equal' => 'Tanggal maintenance harus setelah atau sama dengan tanggal selesai.',
            'maintenance_date.required'       => 'Tanggal maintenance wajib diisi.',
            'project_leader_id.required'      => 'Project Leader wajib dipilih.',
            'project_leader_id.integer'       => 'Project Leader tidak valid.',
            'company_id.required'             => 'Perusahaan wajib dipilih.',
            'company_id.integer'              => 'Perusahaan tidak valid.',
            'support_teams.required'           => 'Timduk wajib diisi.',
        ];
    }

    /**
     * Build a toast message from the API's field-level validation errors,
     * falling back to the API message or a generic message.
     */
    protected function apiErrorMessage(array $body, string $fallback): string
    {
        $messages = collect($body['errors'] ?? [])
            ->flatten()
            ->filter()
            ->all();

        if (count($messages) > 0) {
            return implode("\n", $messages);
        }

        return $body['message'] ?? $fallback;
    }

    /**
     * Merge field-level validation errors returned by the external API
     * into Livewire's error bag so they display on the matching fields.
     *
     * @param  array<string, array<int, string>|string>|string|null  $errors
     */
    protected function mergeApiErrors(mixed $errors): void
    {
        if (! is_array($errors)) {
            return;
        }

        foreach ($errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError($field, $message);
            }
        }
    }

    public function store(): void
    {
        $this->submitting = true;

        try {
            $this->validate();

            $payload = array_filter([
                'name'              => $this->name,
                'code'              => $this->code,
                'contract_number'   => $this->contract_number ?: null,
                'contract_date'     => $this->contract_date ?: null,
                'client'            => $this->client ?: null,
                'ppk'               => $this->ppk ?: null,
                'value'             => $this->value !== '' && $this->value !== null ? (int) $this->value : null,
                'status'            => $this->status,
                'start_date'        => $this->start_date ?: null,
                'end_date'          => $this->end_date ?: null,
                'maintenance_date'  => $this->maintenance_date ?: null,
                'project_leader_id' => (int) $this->project_leader_id,
                'company_id'        => (int) $this->company_id,
            ], fn ($value) => $value !== null);

            $payload['support_teams'] = $this->support_teams;

            $result = app(ProjectWriter::class)->createProject($payload);

            if ($result['ok']) {
                $id = $result['body']['data']['id'] ?? $result['body']['data'][0]['id'] ?? null;
                Toaster::success('Proyek berhasil dibuat!');

                if ($id) {
                    $this->redirect(route('projects.show', $id), navigate: true);
                } else {
                    $this->redirect(route('projects'), navigate: true);
                }
                return;
            }

            $this->mergeApiErrors($result['body']['errors'] ?? null);

            Toaster::error($this->apiErrorMessage($result['body'], 'Gagal membuat proyek. Coba lagi.'));
        } finally {
            $this->submitting = false;
        }
    }
}
?>

<div class="mx-auto space-y-6 py-6 px-2">

    {{-- Breadcrumb --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('projects')" wire:navigate>Projects</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Tambah Proyek</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Page Heading --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Tambah Proyek Baru</flux:heading>
            <flux:description>Isi formulir di bawah untuk mendaftarkan proyek baru.</flux:description>
        </div>
        <flux:button variant="ghost" icon="arrow-left" :href="route('projects')" wire:navigate class="hidden sm:inline-flex">
            Kembali
        </flux:button>
    </div>

    <form wire:submit="store" class="space-y-6">

        {{-- Section 1: Informasi Proyek --}}
        <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                    <flux:icon name="folder" class="w-4 h-4 text-blue-600"/>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-800">Informasi Proyek</p>
                    <p class="text-xs text-zinc-500">Nama, kode, klien, dan status</p>
                </div>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <flux:field>
                        <flux:label>Nama Proyek <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                        <flux:input wire:model="name" placeholder="Masukkan nama proyek..."/>
                        <flux:error name="name"/>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Kode Proyek <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="code" placeholder="Contoh: P15"/>
                    <flux:error name="code"/>
                </flux:field>

                <flux:field>
                    <flux:label>Status <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:select wire:model="status">
                        <flux:select.option value="WAITING">Menunggu</flux:select.option>
                        <flux:select.option value="ON PROGRESS">Berjalan</flux:select.option>
                        <flux:select.option value="CLOSED">Selesai</flux:select.option>
                        <flux:select.option value="MAINTENANCE" disabled>Maintenance</flux:select.option>
                    </flux:select>
                    <flux:error name="status"/>
                </flux:field>

                <flux:field>
                    <flux:label>Nama Klien <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="client" placeholder="Contoh: Kejaksaan Agung"/>
                    <flux:error name="client"/>
                </flux:field>

                <flux:field>
                    <flux:label>Nama PPK <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="ppk" placeholder="Contoh: Nanang Suherman, S.T.MM"/>
                    <flux:error name="ppk"/>
                </flux:field>
            </div>
        </div>

        {{-- Section 2: Kontrak --}}
        <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                    <flux:icon name="document-check" class="w-4 h-4 text-amber-600"/>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-800">Detail Kontrak</p>
                    <p class="text-xs text-zinc-500">Nomor kontrak, tanggal, dan nilai</p>
                </div>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>Nomor Kontrak <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="contract_number" placeholder="Contoh: 008"/>
                    <flux:error name="contract_number"/>
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal Kontrak <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="contract_date" type="date"/>
                    <flux:error name="contract_date"/>
                </flux:field>

                <div class="sm:col-span-2">
                    <flux:field>
                        <flux:label>Nilai Kontrak (Rupiah) <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                        <x-rupiah-input model="value" placeholder="8.000.000.000" />
                        <flux:error name="value"/>
                    </flux:field>
                </div>
            </div>
        </div>

        {{-- Section 3: Timeline --}}
        <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                    <flux:icon name="calendar-days" class="w-4 h-4 text-green-600"/>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-800">Timeline Proyek</p>
                    <p class="text-xs text-zinc-500">Tanggal mulai, selesai, dan maintenance</p>
                </div>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                <flux:field>
                    <flux:label>Tanggal Mulai <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="start_date" type="date"/>
                    <flux:error name="start_date"/>
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal Selesai <flux:badge size="sm" color="red" class="ml-1">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="end_date" type="date" :min="$start_date ?: null"/>
                    <flux:error name="end_date"/>
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal Maintenance</flux:label>
                    <flux:input wire:model="maintenance_date" type="date" :min="$end_date ?: null"/>
                    <flux:error name="maintenance_date"/>
                </flux:field>
            </div>
        </div>

        {{-- Section 4: Perusahaan & Tim --}}
        <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-visible">
            <div class="px-6 py-4 border-b border-zinc-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                    <flux:icon name="users" class="w-4 h-4 text-purple-600"/>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-800">Perusahaan & Tim</p>
                    <p class="text-xs text-zinc-500">Pilih perusahaan dan project leader</p>
                </div>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5 ">

                {{-- Company Select Search --}}
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1.5">
                        Perusahaan
                        <span class="ml-1 inline-flex items-center rounded-md bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700">Wajib</span>
                    </label>
                    <x-search-select
                        model="company_id"
                        :options="$this->companyOptions"
                        placeholder="Cari perusahaan..."
                        search-placeholder="Ketik untuk mencari..."
                    />
                    @error('company_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Project Leader Select Search --}}
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1.5">
                        Project Leader
                        <span class="ml-1 inline-flex items-center rounded-md bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700">Wajib</span>
                    </label>
                    <x-search-select
                        model="project_leader_id"
                        :options="$this->leaderOptions"
                        :avatar="true"
                        placeholder="Cari project leader..."
                        search-placeholder="Ketik untuk mencari..."
                    />
                    @error('project_leader_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>

        {{-- Section 5: Tim Pendukung (PPK) --}}
        <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                    <flux:icon name="briefcase" class="w-4 h-4 text-amber-600"/>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-800">Tim Pendukung (PPK)</p>
                    <p class="text-xs text-zinc-500">Daftar nama tim pendukung — opsional, bisa ditambah nanti</p>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex flex-col sm:flex-row gap-2">
                    <flux:input
                        wire:model="newSupportTeam"
                        wire:keydown.enter.prevent="addSupportTeam"
                        placeholder="Contoh: Nanang Suherman, S.T.MM"
                        icon="user"
                        class="flex-1"
                    />
                    <flux:button type="button" wire:click="addSupportTeam" variant="filled" icon="plus">
                        Tambah
                    </flux:button>
                </div>

                @if (count($support_teams) > 0)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($support_teams as $i => $team)
                            <span wire:key="support-team-{{ $i }}"
                                  class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-amber-50 text-amber-800 text-sm border border-amber-200">
                                <flux:icon name="user" class="w-3.5 h-3.5" />
                                {{ $team }}
                                <button type="button"
                                        wire:click="removeSupportTeam({{ $i }})"
                                        class="ml-0.5 inline-flex items-center justify-center w-5 h-5 rounded-full hover:bg-amber-200 text-amber-700 transition">
                                    <flux:icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6 px-4 rounded-lg border border-dashed border-zinc-300 bg-zinc-50/50">
                        <flux:icon name="user-group" class="w-6 h-6 mx-auto text-zinc-400 mb-1.5" />
                        <p class="text-xs text-zinc-500">Belum ada tim pendukung ditambahkan</p>
                    </div>
                @endif
                <flux:error name="support_teams" />
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
            <flux:button variant="ghost" :href="route('projects')" wire:navigate>
                Batal
            </flux:button>
            <flux:button type="submit" variant="primary" :disabled="$submitting">
                    <flux:icon wire:loading.remove wire:target="store" name="plus-circle" variant="solid" class="size-5"/>
                    <span wire:loading.remove wire:target="store">Simpan Proyek</span>
                    <span wire:loading wire:target="store" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                            <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a10 10 0 00-10 10h4z"/>
                        </svg>
                    </span>
                    <span wire:loading wire:target="store" class="flex items-center gap-2">
                        Menyimpan...
                    </span>
                </flux:button>
        </div>

    </form>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
