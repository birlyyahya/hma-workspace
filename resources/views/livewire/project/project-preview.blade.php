<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.preview', ['title' => 'Project Preview'])]
class extends Component {
    public $id;
    public array $project = [];
    public bool $loading = true;
    public ?string $error = null;

    public function mount(): void
    {
        try {
            $apiProject = rtrim(config('services.api_project'), '/');
            $response = Http::get($apiProject.'/projects/'.$this->id)->json();
            $data = $response['data'] ?? null;

            if (is_array($data) && array_is_list($data)) {
                $data = $data[0] ?? null;
            }

            if (! is_array($data)) {
                $this->error = 'Project tidak ditemukan.';

                return;
            }

            $this->project = $data;
        } catch (\Throwable $e) {
            $this->error = 'Gagal memuat data project.';
            Log::error('Project preview API failed', [
                'id' => $this->id,
                'message' => $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function getStatusColorProperty(): string
    {
        return match ($this->project['status'] ?? null) {
            'ON PROGRESS' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'WAITING' => 'bg-amber-50 text-amber-800 ring-amber-200',
            'CLOSED' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'COMPLETED' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            default => 'bg-slate-50 text-slate-700 ring-slate-200',
        };
    }
}; ?>

<div>
    <div class="min-h-screen bg-linear-to-b from-slate-50 to-slate-100/60 px-4 py-8 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-5xl">

            {{-- Toolbar --}}
            <div class="mb-6 flex items-center justify-end gap-2 print:hidden">
                <button
                    type="button"
                    x-data="{
                        copied: false,
                        copy() {
                            const url = window.location.href;
                            const done = (ok) => {
                                if (ok) {
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                } else {
                                    alert('Gagal menyalin. Salin manual: ' + url);
                                }
                            };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(url).then(() => done(true)).catch(() => done(false));
                                return;
                            }
                            try {
                                const ta = document.createElement('textarea');
                                ta.value = url;
                                ta.setAttribute('readonly', '');
                                ta.style.position = 'fixed';
                                ta.style.opacity = '0';
                                document.body.appendChild(ta);
                                ta.select();
                                const ok = document.execCommand('copy');
                                document.body.removeChild(ta);
                                done(ok);
                            } catch (e) {
                                done(false);
                            }
                        }
                    }"
                    @click="copy()"
                    class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50"
                >
                    <flux:icon name="link" class="size-4" />
                    <span x-text="copied ? 'Tersalin!' : 'Salin link'"></span>
                </button>
                <button
                    type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50"
                >
                    <flux:icon name="printer" class="size-4" />
                    Cetak
                </button>
            </div>

            @if($loading)
                <div class="rounded-3xl bg-white p-8 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200/70">
                    Memuat data project...
                </div>
            @elseif($error)
                <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200/70">
                    <h1 class="text-xl font-semibold text-slate-900">{{ $error }}</h1>
                    <p class="mt-2 text-sm text-slate-600">Pastikan ID project benar atau coba lagi nanti.</p>
                </div>
            @else
                {{-- COVER --}}
                <article class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200/70">
                    <div class="relative bg-linear-to-r from-slate-900 via-slate-800 to-slate-900 px-8 py-10 text-white sm:px-10">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Project Preview</p>
                                <h1 class="mt-2 text-2xl font-semibold leading-tight tracking-tight sm:text-3xl">
                                    {{ $project['name'] ?? 'Untitled Project' }}
                                </h1>
                                @if(! empty($project['code']))
                                    <p class="mt-1 text-sm text-slate-300">Kode: <span class="font-semibold text-white">{{ $project['code'] }}</span></p>
                                @endif
                            </div>

                            <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white ring-1 ring-white/20 backdrop-blur">
                                {{ $project['status'] ?? '—' }}
                            </span>
                        </div>

                        @if(isset($project['progress']))
                            <div class="mt-6">
                                <div class="flex items-center justify-between text-xs font-semibold text-slate-300">
                                    <span>Progress</span>
                                    <span>{{ (int) $project['progress'] }}%</span>
                                </div>
                                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/10">
                                    <div class="h-full rounded-full bg-emerald-400" style="width: {{ min(100, max(0, (int) $project['progress'])) }}%;"></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- BODY --}}
                    <div class="grid grid-cols-1 gap-8 p-8 sm:p-10 lg:grid-cols-3">

                        {{-- Main info --}}
                        <div class="space-y-8 lg:col-span-2">
                            <section>
                                <h2 class="text-xs font-semibold uppercase tracking-widest text-slate-500">Informasi Kontrak</h2>
                                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500">Client</dt>
                                        <dd class="mt-1 text-sm text-slate-900">{{ $project['client'] ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500">PPK</dt>
                                        <dd class="mt-1 text-sm text-slate-900">{{ $project['ppk'] ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500">Nomor Kontrak</dt>
                                        <dd class="mt-1 text-sm text-slate-900">{{ $project['contract_number'] ?: '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500">Tanggal Kontrak</dt>
                                        <dd class="mt-1 text-sm text-slate-900">
                                            @php
                                                $contractDate = $project['contract_date'] ?? null;
                                                $valid = $contractDate && $contractDate !== '0000-00-00';
                                            @endphp
                                            {{ $valid ? Carbon::parse($contractDate)->locale('id')->translatedFormat('d F Y') : '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500">Mulai</dt>
                                        <dd class="mt-1 text-sm text-slate-900">
                                            {{ ! empty($project['start_date']) ? Carbon::parse($project['start_date'])->locale('id')->translatedFormat('d F Y') : '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500">Selesai</dt>
                                        <dd class="mt-1 text-sm text-slate-900">
                                            {{ ! empty($project['end_date']) ? Carbon::parse($project['end_date'])->locale('id')->translatedFormat('d F Y') : '—' }}
                                        </dd>
                                    </div>
                                    @if(! empty($project['maintenance_date']))
                                        <div>
                                            <dt class="text-xs font-semibold text-slate-500">Masa Pemeliharaan</dt>
                                            <dd class="mt-1 text-sm text-slate-900">
                                                {{ Carbon::parse($project['maintenance_date'])->locale('id')->translatedFormat('d F Y') }}
                                            </dd>
                                        </div>
                                    @endif
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500">Nilai</dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                                            Rp {{ number_format((float) ($project['value'] ?? 0), 0, ',', '.') }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section>
                                <h2 class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tim Project</h2>

                                <div class="mt-4 space-y-4">
                                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50/40 p-4">
                                        <p class="text-xs font-semibold text-slate-500">Project Leader</p>
                                        <div class="mt-2 flex items-center gap-3">
                                            <flux:avatar circle name="{{ $project['project_leader_name'] ?? 'Unknown' }}" size="sm" />
                                            <span class="text-sm font-semibold text-slate-900">
                                                {{ $project['project_leader_name'] ?? '—' }}
                                            </span>
                                        </div>
                                    </div>

                                    @php
                                        $internals = $project['support_team_internals'] ?? [];
                                        $externals = $project['support_teams'] ?? [];
                                    @endphp

                                    @if(! empty($internals))
                                        <div class="rounded-2xl border border-slate-200/70 p-4">
                                            <p class="text-xs font-semibold text-slate-500">Tim Internal</p>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach($internals as $member)
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                        <flux:avatar circle name="{{ $member['user_name'] ?? '?' }}" size="xs" />
                                                        <span>{{ $member['user_name'] ?? '—' }}</span>
                                                        @if(! empty($member['user_username']))
                                                            <span class="font-normal text-slate-400">@<!-- -->{{ $member['user_username'] }}</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if(! empty($externals))
                                        <div class="rounded-2xl border border-slate-200/70 p-4">
                                            <p class="text-xs font-semibold text-slate-500">Tim Pendukung</p>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach($externals as $name)
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                        <flux:avatar circle name="{{ $name }}" size="xs" />
                                                        <span>{{ $name }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </section>

                            @if(! empty($project['specktech']))
                                <section>
                                    <h2 class="text-xs font-semibold uppercase tracking-widest text-slate-500">Spectech</h2>
                                    <ul class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach($project['specktech'] as $spec)
                                            <li class="rounded-xl border border-slate-200/70 bg-white px-4 py-3 text-sm text-slate-800">
                                                {{ $spec['name'] ?? ($spec['title'] ?? json_encode($spec)) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                        </div>

                        {{-- Side: Company / director --}}
                        <aside class="space-y-6">
                            <section class="rounded-2xl border border-slate-200/70 bg-slate-50/40 p-5">
                                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Perusahaan</p>
                                <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ $project['company_name'] ?? '—' }}</h3>
                                @if(! empty($project['company_address']))
                                    <p class="mt-2 whitespace-pre-line text-xs leading-relaxed text-slate-600">
                                        {{ $project['company_address'] }}
                                    </p>
                                @endif
                            </section>

                            <section class="rounded-2xl border border-slate-200/70 bg-white p-5 text-center">
                                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Direktur</p>

                                @if(! empty($project['company_director_signature']))
                                    <div class="mx-auto mt-4 flex h-24 items-center justify-center">
                                        <img
                                            src="{{ str_starts_with($project['company_director_signature'], 'http') ? $project['company_director_signature'] : rtrim(config('services.api_project_url') ?? config('services.api_project'), '/').$project['company_director_signature'] }}"
                                            alt="Signature"
                                            class="max-h-24 object-contain"
                                            onerror="this.style.display='none'"
                                        >
                                    </div>
                                @endif

                                <p class="mt-3 text-sm font-semibold text-slate-900">
                                    {{ $project['company_director_name'] ?? '—' }}
                                </p>
                                @if(! empty($project['company_director_phone']))
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $project['company_director_phone'] }}</p>
                                @endif
                            </section>

                            <section class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500">
                                <p>
                                    Terakhir diperbarui:
                                    <span class="font-semibold text-slate-700">
                                        {{ ! empty($project['updated_at']) ? Carbon::parse($project['updated_at'])->locale('id')->translatedFormat('d F Y, H:i') : '—' }}
                                    </span>
                                </p>
                            </section>
                        </aside>
                    </div>
                </article>
            @endif

        </div>
    </div>
</div>
