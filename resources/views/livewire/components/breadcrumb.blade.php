<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    /** @var list<array{label: string, url: string|null}> */
    public array $items = [];

    public function mount(?string $routeName = null): void
    {
        $this->items = $this->buildTrail($routeName ?? request()->route()?->getName());
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2?: string}>
     */
    protected function map(): array
    {
        return [
            'dashboard' => ['Dashboard', null],

            'projects' => ['Proyek', null],
            'perusahaan' => ['Perusahaan', 'projects'],
            'projects.create' => ['Tambah Proyek', 'projects'],
            'projects.edit' => ['Edit Proyek', 'projects'],
            'projects.show' => ['Detail Proyek', 'projects'],
            'projects.preview' => ['Pratinjau Proyek', 'projects'],
            'projects.gantt-print' => ['Cetak Gantt', 'projects'],

            'dar' => ['Daily Report', null],
            'dar.dar-show' => ['Detail Aktivitas', 'dar'],

            'izin' => ['Izin', null],
            'izin.quick' => ['Izin Cepat', 'izin'],
            'izin.show' => ['Detail Izin', 'izin'],
            'izin.pdf' => ['PDF Izin', 'izin'],
            'izin.laporan-pengajuan' => ['Laporan Pengajuan', 'izin'],
            'izin.spd-preview' => ['Preview SPD', 'izin'],

            'inventaris' => ['Inventaris', null],

            'cashadvance' => ['Cash Advance', null],
            'cashadvance.quick' => ['Catat Cepat', 'cashadvance'],
            'cashadvance.dompet-show' => ['Dompet Kegiatan', 'cashadvance'],
            'cashadvance.laporan.capl' => ['Laporan CA-PL', 'cashadvance'],
            'cashadvance.laporan.kegiatan' => ['Laporan Kegiatan', 'cashadvance'],

            'events' => ['Event', null],
            'events.show' => ['Detail Event', 'events'],
            'event.scan' => ['Scan Kehadiran', 'events'],
            'event.registration' => ['Registrasi', 'events'],

            'knowledge' => ['Knowledge', null],
            'knowledge.articles' => ['Artikel', 'knowledge'],
            'knowledge.articles-create' => ['Buat Artikel', 'knowledge.articles'],
            'knowledge.articles-edit' => ['Edit Artikel', 'knowledge.articles'],
            'knowledge.articles-show' => ['Detail Artikel', 'knowledge.articles'],
            'knowledge.announcements' => ['Pengumuman', 'knowledge'],
            'knowledge.policies' => ['Kebijakan', 'knowledge'],
            'knowledge.documentation' => ['Dokumentasi', 'knowledge'],

            'users' => ['Manajemen User', null],

            'settings' => ['Pengaturan', null, 'profile.edit'],
            'profile.edit' => ['Profil', 'settings'],
            'user-password.edit' => ['Kata Sandi', 'settings'],
            'appearance.edit' => ['Tampilan', 'settings'],
            'notifications.edit' => ['Notifikasi', 'settings'],
        ];
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    protected function buildTrail(?string $routeName): array
    {
        $map = $this->map();
        $trail = [];

        if ($routeName === null) {
            return [['label' => 'Dashboard', 'url' => null]];
        }

        if (! isset($map[$routeName])) {
            $label = Str::headline(Str::afterLast($routeName, '.'));

            return [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => $label, 'url' => null],
            ];
        }

        $current = $routeName;
        $isCurrentPage = true;

        while ($current !== null && isset($map[$current])) {
            [$label, $parent] = $map[$current];
            $linkRoute = $map[$current][2] ?? $current;

            array_unshift($trail, [
                'label' => $label,
                'url' => ! $isCurrentPage && Route::has($linkRoute) ? route($linkRoute) : null,
            ]);

            $current = $parent;
            $isCurrentPage = false;
        }

        if ($routeName !== 'dashboard') {
            array_unshift($trail, ['label' => 'Dashboard', 'url' => route('dashboard')]);
        }

        return $trail;
    }
}

?>

<flux:breadcrumbs class="gap-1">
    @foreach ($items as $item)
        @if ($item['url'] !== null)
            <flux:breadcrumbs.item wire:key="crumb-{{ $loop->index }}" :href="$item['url']" wire:navigate class="text-zinc-500">
                {{ $item['label'] }}
            </flux:breadcrumbs.item>
        @else
            <flux:breadcrumbs.item wire:key="crumb-{{ $loop->index }}" class="font-medium text-zinc-900">
                {{ $item['label'] }}
            </flux:breadcrumbs.item>
        @endif
    @endforeach
</flux:breadcrumbs>
