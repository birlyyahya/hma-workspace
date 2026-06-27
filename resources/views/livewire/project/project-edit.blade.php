<?php

use App\Models\User;
use App\Services\ProjectCache;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Masmerise\Toaster\Toaster;

new class extends Component
{
    public int $id;

    public string $name             = '';
    public string $code             = '';
    public string $contract_number  = '';
    public string $contract_date    = '';
    public string $client           = '';
    public string $ppk              = '';
    public string $value            = '';
    public string $status           = 'WAITING';
    public string $start_date       = '';
    public string $end_date         = '';
    public string $maintenance_date = '';
    public string $project_leader_id = '';
    public string $company_id       = '';

    public array $support_teams = [];
    public string $newSupportTeam = '';

    public bool $loading    = true;
    public bool $submitting = false;
    public bool $notFound   = false;

    public function mount(int $id): void
    {
        $this->authorize('project.update');
        $this->id = $id;
        $this->fetchProject();
    }

    public function fetchProject(): void
    {
        try {
            $response = Http::timeout(15)->retry(2, 200)->get(
                rtrim((string) config('services.api_project'), '/').'/projects/'.$this->id
            )->json();

            $project = collect($response['data'] ?? [])->first();

            if (! $project) {
                $this->notFound = true;
                $this->loading = false;
                return;
            }

            $this->name              = (string) ($project['name'] ?? '');
            $this->code              = (string) ($project['code'] ?? '');
            $this->contract_number   = (string) ($project['contract_number'] ?? '');
            $this->contract_date     = $this->normalizeDate($project['contract_date'] ?? null);
            $this->client            = (string) ($project['client'] ?? '');
            $this->ppk               = (string) ($project['ppk'] ?? '');
            $this->value             = (string) ($project['value'] ?? '');
            $this->status            = (string) ($project['status'] ?? 'WAITING');
            $this->start_date        = $this->normalizeDate($project['start_date'] ?? null);
            $this->end_date          = $this->normalizeDate($project['end_date'] ?? null);
            $this->maintenance_date  = $this->normalizeDate($project['maintenance_date'] ?? null);
            $this->project_leader_id = (string) ($project['project_leader_id'] ?? '');
            $this->company_id        = (string) ($project['company_id'] ?? '');
            $this->support_teams     = array_values($project['support_teams'] ?? []);

            $this->loading = false;
        } catch (\Throwable $e) {
            Log::error('Failed to load project for edit', ['id' => $this->id, 'error' => $e->getMessage()]);
            $this->notFound = true;
            $this->loading = false;
        }
    }

    protected function normalizeDate(?string $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
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
            'maintenance_date'  => ['required', 'date', 'after_or_equal:end_date'],
            'project_leader_id' => ['required', 'integer'],
            'company_id'        => ['required', 'integer'],
            'support_teams'     => ['array'],
            'support_teams.*'   => ['string', 'min:2'],
        ];
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
            'project_leader_id.required'      => 'Project Leader wajib dipilih.',
            'project_leader_id.integer'       => 'Project Leader tidak valid.',
            'company_id.required'             => 'Perusahaan wajib dipilih.',
            'company_id.integer'              => 'Perusahaan tidak valid.',
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

    /**
     * The external API returns HTTP 200 even on logical failure, signalling
     * the real outcome through the `status` field in the response body.
     */
    protected function apiSucceeded(\Illuminate\Http\Client\Response $response): bool
    {
        $status = $response->json('status');

        if ($status === null) {
            return $response->successful();
        }

        return in_array((int) $status, [200, 201], true);
    }

    /**
     * Build a toast message from the API's field-level validation errors,
     * falling back to the API message or a generic message.
     */
    protected function apiErrorMessage(\Illuminate\Http\Client\Response $response, string $fallback): string
    {
        $messages = collect($response->json('errors'))
            ->flatten()
            ->filter()
            ->all();

        if (count($messages) > 0) {
            return implode("\n", $messages);
        }

        return $response->json('message') ?? $fallback;
    }

    /**
     * Merge field-level validation errors returned by the external API
     * into Livewire's error bag so they display on the matching fields.
     *
     * @param  array<string, array<int, string>|string>|null  $errors
     */
    protected function mergeApiErrors(?array $errors): void
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

    public function update(): void
    {
        $this->submitting = true;

        try {
            $this->validate();

            $response = Http::patch(config('services.api_project').'projects/'.$this->id, [
                'name'              => $this->name,
                'code'              => $this->code,
                'contract_number'   => $this->contract_number,
                'contract_date'     => $this->contract_date,
                'client'            => $this->client,
                'ppk'               => $this->ppk,
                'value'             => (int) $this->value,
                'status'            => $this->status,
                'start_date'        => $this->start_date,
                'end_date'          => $this->end_date,
                'maintenance_date'  => $this->maintenance_date ?: null,
                'project_leader_id' => (int) $this->project_leader_id,
                'company_id'        => (int) $this->company_id,
                'support_teams'     => $this->support_teams,
                ]);


            if ($this->apiSucceeded($response)) {
                app(ProjectCache::class)->flushProjects();

                Toaster::success('Proyek berhasil diperbarui!');
                $this->redirect(route('projects.show', $this->id), navigate: true);
                return;
            }

            $this->mergeApiErrors($response->json('errors'));

            Toaster::error($this->apiErrorMessage($response, 'Gagal memperbarui proyek. Coba lagi.'));
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
        <flux:breadcrumbs.item :href="route('projects.show', $id)" wire:navigate>{{ $code ?: '#'.$id }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Edit Proyek</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Page Heading --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Edit Proyek</flux:heading>
            <flux:description>Perbarui informasi proyek di bawah ini.</flux:description>
        </div>
        <flux:button variant="ghost" icon="arrow-left" :href="route('projects.show', $id)" wire:navigate class="hidden sm:inline-flex">
            Kembali
        </flux:button>
    </div>

    @if ($notFound)
        <div class="bg-white rounded-2xl border border-red-200 p-10 text-center">
            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-3">
                <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600" />
            </div>
            <p class="text-sm font-semibold text-zinc-800">Proyek tidak ditemukan</p>
            <p class="text-xs text-zinc-500 mt-1">Data proyek tidak dapat dimuat atau sudah dihapus.</p>
            <flux:button :href="route('projects')" wire:navigate variant="primary" class="mt-4">
                Kembali ke Daftar Proyek
            </flux:button>
        </div>
    @elseif ($loading)
        <div class="bg-white rounded-2xl border border-zinc-100 p-10 text-center">
            <flux:icon name="arrow-path" class="w-6 h-6 text-zinc-400 mx-auto animate-spin" />
            <p class="text-sm text-zinc-500 mt-2">Memuat data proyek...</p>
        </div>
    @else
        <form wire:submit="update" class="space-y-6">

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
                            <flux:select.option value="WAITING">WAITING</flux:select.option>
                            <flux:select.option value="ON PROGRESS">ON PROGRESS</flux:select.option>
                            <flux:select.option value="CLOSED">CLOSED</flux:select.option>
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
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

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
                        <p class="text-xs text-zinc-500">Daftar nama tim pendukung</p>
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
                <flux:button variant="ghost" :href="route('projects.show', $id)" wire:navigate>
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" :disabled="$submitting">
                    <flux:icon wire:loading.remove wire:target="update" name="check-circle" variant="solid" class="size-5"/>
                    <span wire:loading.remove wire:target="update">Simpan Perubahan</span>
                    <span wire:loading wire:target="update" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                            <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a10 10 0 00-10 10h4z"/>
                        </svg>
                    </span>
                    <span wire:loading wire:target="update" class="flex items-center gap-2">
                        Menyimpan...
                    </span>
                </flux:button>
            </div>

        </form>
    @endif
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
