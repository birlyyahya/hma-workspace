<?php

use App\Jobs\DeleteProjectFilesJob;
use App\Jobs\MoveProjectFilesJob;
use App\Models\ProjectFolder;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component
{
    /**
     * Kategori file diturunkan dari ekstensi (bukan dari kategori BEPM).
     *
     * @var array<string, array<int, string>>
     */
    protected const CATEGORY_EXTENSIONS = [
        'dokumen' => ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx'],
        'gambar' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'spreadsheet' => ['xls', 'xlsx', 'csv'],
        'arsip' => ['zip', 'rar', '7z'],
    ];

    public $id;

    public bool $forbidden = false;

    public ?int $currentFolderId = null;

    public string $search = '';

    public string $categoryFilter = 'all';

    public string $sortBy = 'date';

    public string $sortDir = 'desc';

    /** @var array<int, string> id dokumen terpilih (bulk) */
    public array $selected = [];

    public string $newFolderName = '';

    public ?int $renamingFolderId = null;

    public string $renameFolderName = '';

    public ?int $movingFolderId = null;

    public ?int $moveFolderTargetId = null;

    public ?int $deletingFolderId = null;

    public int $deletingFolderFileCount = 0;

    public ?int $renamingDocId = null;

    public string $renameDocName = '';

    public ?int $deletingDocId = null;

    public string $deletingDocName = '';

    public ?int $moveSelectedTargetId = null;

    public string $previewUrl = '';

    public string $previewName = '';

    public string $previewExt = '';

    public function placeholder()
    {
        return view('components.placeholder.ph_project_files_tabs');
    }

    #[On('documentLoad')]
    public function mount(): void
    {
        $project = app(ProjectCache::class)->projectFor((int) $this->id);

        $this->forbidden = ! (Auth::user()?->canAccessProject($project) ?? false);
    }

    #[On('file-uploaded')]
    public function onFileUploaded(): void
    {
        // Cache dokumen sudah di-flush oleh endpoint complete; render ulang saja.
    }

    // ------------------------------------------------------------ data sources

    public function getCurrentFolderProperty(): ?ProjectFolder
    {
        return $this->currentFolderId !== null
            ? ProjectFolder::query()->whereKey($this->currentFolderId)->where('project_id', (int) $this->id)->first()
            : null;
    }

    /**
     * @return array<int, ProjectFolder>
     */
    public function getFoldersProperty(): array
    {
        return ProjectFolder::query()
            ->where('project_id', (int) $this->id)
            ->where('parent_id', $this->currentFolderId)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * Breadcrumb dari root sampai folder aktif.
     *
     * @return array<int, array{id: ?int, name: string}>
     */
    public function getBreadcrumbsProperty(): array
    {
        $crumbs = [['id' => null, 'name' => 'Semua File']];
        $chain = [];
        $folder = $this->currentFolder;

        while ($folder !== null) {
            array_unshift($chain, ['id' => $folder->id, 'name' => $folder->name]);
            $folder = $folder->parent;
        }

        return [...$crumbs, ...$chain];
    }

    protected function rootPrefix(): string
    {
        return 'projects/'.(int) $this->id.'/';
    }

    protected function currentPrefix(): string
    {
        $folder = $this->currentFolder;

        return $this->rootPrefix().($folder !== null ? $folder->path().'/' : '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function allDocs(): array
    {
        return app(ProjectCache::class)->documentsFor((int) $this->id);
    }

    /**
     * Baris file di folder aktif, setelah search + filter kategori + sort.
     * File legacy (byte di storage BEPM, url tidak berprefix projects/) hanya
     * tampil di root dan tidak bisa di-rename/move.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilesProperty(): array
    {
        $prefix = $this->currentPrefix();

        $rows = collect($this->allDocs())
            ->map(function (array $doc) {
                $url = (string) data_get($doc, 'files.url', '');
                $path = Str::after($url, '/storage/');
                $legacy = ! str_starts_with($path, $this->rootPrefix());
                $name = $legacy
                    ? (string) ($doc['title'] ?? basename($url))
                    : basename($url);
                $ext = strtolower(Str::afterLast($url, '.'));

                return [
                    'id' => $doc['id'],
                    'name' => $name,
                    'key' => $path,
                    'legacy' => $legacy,
                    'ext' => $ext,
                    'category' => $this->categoryOf($ext),
                    'size' => (string) (data_get($doc, 'files.size') ?? ''),
                    'size_bytes' => $this->sizeToBytes((string) (data_get($doc, 'files.size') ?? '')),
                    'created_at' => (string) ($doc['created_at'] ?? ''),
                    'category_id' => data_get($doc, 'admin_doc_category_id'),
                    'title' => (string) ($doc['title'] ?? ''),
                ];
            })
            ->filter(function (array $row) use ($prefix) {
                if ($row['legacy']) {
                    return $this->currentFolderId === null;
                }

                return str_starts_with($row['key'], $prefix)
                    && ! str_contains(substr($row['key'], strlen($prefix)), '/');
            });

        if ($this->categoryFilter !== 'all') {
            $rows = $rows->filter(fn (array $row) => $row['category'] === $this->categoryFilter);
        }

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['name']), $needle));
        }

        $rows = match ($this->sortBy) {
            'name' => $rows->sortBy(fn (array $row) => mb_strtolower($row['name']), SORT_REGULAR, $this->sortDir === 'desc'),
            'size' => $rows->sortBy('size_bytes', SORT_REGULAR, $this->sortDir === 'desc'),
            default => $rows->sortBy('created_at', SORT_REGULAR, $this->sortDir === 'desc'),
        };

        return $rows->values()->all();
    }

    /**
     * Seluruh folder project sebagai daftar rata berindentasi untuk dropdown
     * tujuan move. $excludeId beserta subtree-nya dibuang (cegah siklus).
     *
     * @return array<int, array{id: ?int, label: string}>
     */
    public function folderOptions(?int $excludeId = null): array
    {
        $all = ProjectFolder::query()
            ->where('project_id', (int) $this->id)
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        $options = [['id' => null, 'label' => 'Root (Semua File)']];

        $walk = function (?int $parentId, int $depth) use (&$walk, &$options, $all, $excludeId) {
            foreach ($all->get($parentId, collect()) as $folder) {
                if ($excludeId !== null && $folder->id === $excludeId) {
                    continue;
                }

                $options[] = ['id' => $folder->id, 'label' => str_repeat('— ', $depth).$folder->name];
                $walk($folder->id, $depth + 1);
            }
        };
        $walk(null, 1);

        return $options;
    }

    public function getHasBusyFolderProperty(): bool
    {
        return ProjectFolder::query()
            ->where('project_id', (int) $this->id)
            ->whereNotNull('status')
            ->exists();
    }

    // ------------------------------------------------------------- navigation

    public function openFolder(?int $folderId): void
    {
        $this->currentFolderId = $folderId;
        $this->reset('selected', 'search');
    }

    public function updatedSearch(): void
    {
        $this->reset('selected');
    }

    public function updatedCategoryFilter(): void
    {
        $this->reset('selected');
    }

    public function sortColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDir = $column === 'date' ? 'desc' : 'asc';
    }

    // ----------------------------------------------------------------- folder

    public function createFolder(): void
    {
        if ($this->forbidden) {
            return;
        }

        $this->validate(
            ['newFolderName' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN][\pL\pN _\-\.\(\)]*$/u']],
            ['newFolderName.regex' => 'Nama folder hanya boleh huruf, angka, spasi, dan karakter - _ . ( ).'],
        );

        $exists = ProjectFolder::query()
            ->where('project_id', (int) $this->id)
            ->where('parent_id', $this->currentFolderId)
            ->where('name', trim($this->newFolderName))
            ->exists();

        if ($exists) {
            $this->addError('newFolderName', 'Folder dengan nama itu sudah ada di sini.');

            return;
        }

        ProjectFolder::query()->create([
            'project_id' => (int) $this->id,
            'parent_id' => $this->currentFolderId,
            'name' => trim($this->newFolderName),
            'created_by' => Auth::id(),
        ]);

        $this->reset('newFolderName');
        Flux::modal('new-folder-modal')->close();
        Toaster::success('Folder dibuat');
    }

    public function startRenameFolder(int $folderId): void
    {
        $folder = $this->ownFolder($folderId);

        if ($folder === null || $folder->status !== null) {
            return;
        }

        $this->renamingFolderId = $folder->id;
        $this->renameFolderName = $folder->name;
        Flux::modal('rename-folder-modal')->show();
    }

    public function renameFolder(): void
    {
        $folder = $this->ownFolder($this->renamingFolderId);

        if ($folder === null || $folder->status !== null) {
            return;
        }

        $this->validate(
            ['renameFolderName' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN][\pL\pN _\-\.\(\)]*$/u']],
            ['renameFolderName.regex' => 'Nama folder hanya boleh huruf, angka, spasi, dan karakter - _ . ( ).'],
        );

        $newName = trim($this->renameFolderName);

        if ($newName === $folder->name) {
            Flux::modal('rename-folder-modal')->close();

            return;
        }

        $duplicate = ProjectFolder::query()
            ->where('project_id', (int) $this->id)
            ->where('parent_id', $folder->parent_id)
            ->where('name', $newName)
            ->whereKeyNot($folder->id)
            ->exists();

        if ($duplicate) {
            $this->addError('renameFolderName', 'Folder dengan nama itu sudah ada di sini.');

            return;
        }

        $this->dispatchFolderMove($folder, ['name' => $newName], $this->replacedPrefixFor($folder, name: $newName));
        $this->reset('renamingFolderId', 'renameFolderName');
        Flux::modal('rename-folder-modal')->close();
    }

    public function startMoveFolder(int $folderId): void
    {
        $folder = $this->ownFolder($folderId);

        if ($folder === null || $folder->status !== null) {
            return;
        }

        $this->movingFolderId = $folder->id;
        $this->moveFolderTargetId = $folder->parent_id;
        Flux::modal('move-folder-modal')->show();
    }

    public function moveFolder(): void
    {
        $folder = $this->ownFolder($this->movingFolderId);

        if ($folder === null || $folder->status !== null) {
            return;
        }

        $targetId = $this->moveFolderTargetId ? (int) $this->moveFolderTargetId : null;

        if ($targetId === $folder->parent_id) {
            Flux::modal('move-folder-modal')->close();

            return;
        }

        $target = $targetId !== null ? $this->ownFolder($targetId) : null;

        if ($targetId !== null && $target === null) {
            Toaster::error('Folder tujuan tidak ditemukan');

            return;
        }

        if (in_array($targetId, $this->subtreeIds($folder), true)) {
            Toaster::error('Tidak bisa memindahkan folder ke dalam dirinya sendiri');

            return;
        }

        $duplicate = ProjectFolder::query()
            ->where('project_id', (int) $this->id)
            ->where('parent_id', $targetId)
            ->where('name', $folder->name)
            ->whereKeyNot($folder->id)
            ->exists();

        if ($duplicate) {
            Toaster::error('Folder dengan nama sama sudah ada di tujuan');

            return;
        }

        $newParentPath = $target !== null ? $target->path().'/' : '';
        $this->dispatchFolderMove($folder, ['parent_id' => $targetId], $this->rootPrefix().$newParentPath.$folder->name.'/');
        $this->reset('movingFolderId', 'moveFolderTargetId');
        Flux::modal('move-folder-modal')->close();
    }

    public function confirmDeleteFolder(int $folderId): void
    {
        $folder = $this->ownFolder($folderId);

        if ($folder === null || $folder->status !== null) {
            return;
        }

        $docs = $this->docsUnderPrefix($this->rootPrefix().$folder->path().'/');
        if ($docs === [] && ! $folder->children()->exists()) {
            dd( $this->allDocs());
            $folder->delete();
            Toaster::success('Folder dihapus');

            return;
        }

        $this->deletingFolderId = $folder->id;
        $this->deletingFolderFileCount = count($docs);
        Flux::modal('delete-folder-modal')->show();
    }

    public function deleteFolder(): void
    {
        $folder = $this->ownFolder($this->deletingFolderId);

        if ($folder === null || $folder->status !== null) {
            return;
        }
        $items = collect($this->docsUnderPrefix($this->rootPrefix().$folder->path().'/'))
            ->map(fn (array $doc) => [
                'doc_id' => (int) $doc['id'],
                'key' => ltrim(Str::after(
                    parse_url((string) data_get($doc, 'files.url', ''), PHP_URL_PATH)
                    ?: (string) data_get($doc, 'files.url', ''),
                    '/storage/'
                    ), '/'),
            ])
            ->values()
            ->all();
        $folder->update(['status' => 'deleting']);

        DeleteProjectFilesJob::dispatch((int) $this->id, $items, $folder->id);

        $this->reset('deletingFolderId', 'deletingFolderFileCount');
        Flux::modal('delete-folder-modal')->close();
        Toaster::success('Penghapusan folder diproses di background');
    }

    // ------------------------------------------------------------------- file

    public function openPreview(int $docId): void
    {
        $row = collect($this->files)->firstWhere('id', $docId);

        if ($row === null) {
            Toaster::error('File tidak ditemukan');

            return;
        }

        try {
            $this->previewUrl = $row['legacy']
                ? rtrim((string) config('services.url_project'), '/').'/'.ltrim($row['key'], '/')
                : app(ProjectFileStorage::class)->presignedGetUrl($row['key'], (int) config('uploads.project_files.presign_ttl'));
        } catch (\Throwable $e) {
            Toaster::error('Gagal menyiapkan preview');

            return;
        }

        $this->previewName = $row['name'];
        $this->previewExt = $row['ext'];
        Flux::modal('file-preview-modal')->show();
    }

    public function startRenameDoc(int $docId): void
    {
        $row = collect($this->files)->firstWhere('id', $docId);

        if ($row === null || $row['legacy']) {
            return;
        }

        $this->renamingDocId = $docId;
        $this->renameDocName = pathinfo($row['name'], PATHINFO_FILENAME);
        Flux::modal('rename-file-modal')->show();
    }

    public function renameDoc(): void
    {
        $row = collect($this->files)->firstWhere('id', $this->renamingDocId);

        if ($row === null || $row['legacy'] || $this->forbidden) {
            return;
        }

        $this->validate(
            ['renameDocName' => ['required', 'string', 'max:200', 'regex:/^[\pL\pN][\pL\pN _\-\.\(\)]*$/u']],
            ['renameDocName.regex' => 'Nama file hanya boleh huruf, angka, spasi, dan karakter - _ . ( ).'],
        );

        $prefix = Str::beforeLast($row['key'], '/').'/';
        $newKey = $prefix.trim($this->renameDocName).'.'.$row['ext'];

        if ($newKey === $row['key']) {
            Flux::modal('rename-file-modal')->close();

            return;
        }

        if ($this->keyExists($newKey)) {
            $this->addError('renameDocName', 'File dengan nama itu sudah ada di folder ini.');

            return;
        }

        MoveProjectFilesJob::dispatch((int) $this->id, [$this->moveItemFor($row, $newKey)]);

        $this->reset('renamingDocId', 'renameDocName');
        Flux::modal('rename-file-modal')->close();
        Toaster::success('Rename diproses di background');
    }

    public function confirmDeleteDoc(int $docId): void
    {
        $row = collect($this->files)->firstWhere('id', $docId);

        if ($row === null) {
            Toaster::error('File tidak ditemukan');

            return;
        }

        $this->deletingDocId = $docId;
        $this->deletingDocName = $row['name'];
        Flux::modal('delete-file-modal')->show();
    }

    public function deleteDoc(): void
    {
        if ($this->deletingDocId === null || $this->forbidden) {
            return;
        }

        $row = collect($this->files)->firstWhere('id', $this->deletingDocId);
        $this->reset('deletingDocId', 'deletingDocName');
        Flux::modal('delete-file-modal')->close();

        if ($row === null) {
            return;
        }

        if ($this->deleteOne($row)) {
            Toaster::success('File dihapus');
            $this->dispatch('file-uploaded');
            app(ProjectCache::class)->flushDocs((int) $this->id);
        } else {
            Toaster::error('Hapus file gagal');
        }


    }

    // ------------------------------------------------------------------- bulk

    public function deleteSelected(): void
    {
        if ($this->forbidden || $this->selected === []) {
            return;
        }

        $rows = collect($this->files)->whereIn('id', array_map('intval', $this->selected));
        $failed = 0;

        foreach ($rows as $row) {
            if (! $this->deleteOne($row)) {
                $failed++;
            }
        }

        app(ProjectCache::class)->flushDocs((int) $this->id);
        $this->reset('selected');

        if ($failed === 0) {
            Toaster::success('File terpilih dihapus');
        } else {
            Toaster::error("{$failed} file gagal dihapus");
        }
    }

    public function moveSelected(): void
    {
        if ($this->forbidden || $this->selected === []) {
            return;
        }

        $targetId = $this->moveSelectedTargetId ? (int) $this->moveSelectedTargetId : null;
        $target = $targetId !== null ? $this->ownFolder($targetId) : null;

        if ($targetId !== null && $target === null) {
            Toaster::error('Folder tujuan tidak ditemukan');

            return;
        }

        $prefix = $this->rootPrefix().($target !== null ? $target->path().'/' : '');
        $rows = collect($this->files)->whereIn('id', array_map('intval', $this->selected));
        $items = [];
        $skippedLegacy = 0;

        foreach ($rows as $row) {
            if ($row['legacy']) {
                $skippedLegacy++;

                continue;
            }

            $newKey = $this->availableKey($prefix, $row['name']);

            if ($newKey !== $row['key']) {
                $items[] = $this->moveItemFor($row, $newKey);
            }
        }

        if ($items !== []) {
            MoveProjectFilesJob::dispatch((int) $this->id, $items);
        }

        $this->reset('selected', 'moveSelectedTargetId');
        Flux::modal('move-selected-modal')->close();

        if ($skippedLegacy > 0) {
            Toaster::warning("{$skippedLegacy} file lama dilewati (tidak bisa dipindah), sisanya diproses di background");
        } else {
            Toaster::success('Pemindahan diproses di background');
        }
    }

    // ---------------------------------------------------------------- helpers

    protected function ownFolder(?int $folderId): ?ProjectFolder
    {
        if ($folderId === null || $this->forbidden) {
            return null;
        }

        return ProjectFolder::query()
            ->whereKey($folderId)
            ->where('project_id', (int) $this->id)
            ->first();
    }

    /**
     * @return array<int, int> id folder + seluruh turunannya
     */
    protected function subtreeIds(ProjectFolder $folder): array
    {
        $byParent = ProjectFolder::query()
            ->where('project_id', (int) $this->id)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $stack = [$folder->id];

        while ($stack !== []) {
            $current = array_pop($stack);
            $ids[] = $current;

            foreach ($byParent->get($current, collect()) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function docsUnderPrefix(string $prefix): array
    {
        return collect($this->allDocs())
            ->filter(function (array $doc) use ($prefix) {
                $url = (string) data_get($doc, 'files.url', '');

                $path = ltrim(Str::after(parse_url($url, PHP_URL_PATH) ?: $url, '/storage/'), '/');

                return str_starts_with($path, $prefix);
            })
            ->values()
            ->all();
    }

    protected function keyExists(string $key): bool
    {
        return collect($this->allDocs())
            ->contains(fn (array $doc) => (string) data_get($doc, 'files.url', '') === $key);
    }

    protected function availableKey(string $prefix, string $filename): string
    {
        $key = $prefix.$filename;

        if (! $this->keyExists($key)) {
            return $key;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $suffix = 1;

        do {
            $key = $prefix.$base." ({$suffix})".($ext !== '' ? ".{$ext}" : '');
            $suffix++;
        } while ($this->keyExists($key));

        return $key;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{doc_id: int, from_key: string, to_key: string, title: string, category_id: ?int}
     */
    protected function moveItemFor(array $row, string $newKey): array
    {
        $categoryId = $row['category_id'] !== null
            ? (int) $row['category_id']
            : (int) (collect(app(ProjectCache::class)->docCategories())->first()['id'] ?? 0);

        return [
            'doc_id' => (int) $row['id'],
            'from_key' => $row['key'],
            'to_key' => $newKey,
            'title' => $row['title'] !== '' ? $row['title'] : pathinfo(basename($newKey), PATHINFO_FILENAME),
            'category_id' => $categoryId,
        ];
    }

    /**
     * Prefix baru subtree folder setelah rename (nama diganti, parent tetap).
     */
    protected function replacedPrefixFor(ProjectFolder $folder, string $name): string
    {
        $parentPath = $folder->parent !== null ? $folder->parent->path().'/' : '';

        return $this->rootPrefix().$parentPath.$name.'/';
    }

    /**
     * Kunci folder, susun item pemindahan untuk seluruh file di subtree, lalu
     * serahkan ke MoveProjectFilesJob (folderUpdate diterapkan job saat sukses).
     *
     * @param  array{name?: string, parent_id?: ?int}  $folderUpdate
     */
    protected function dispatchFolderMove(ProjectFolder $folder, array $folderUpdate, string $newPrefix): void
    {
        $oldPrefix = $this->rootPrefix().$folder->path().'/';

        $items = collect($this->docsUnderPrefix($oldPrefix))
            ->map(function (array $doc) use ($oldPrefix, $newPrefix) {
                $key = (string) data_get($doc, 'files.url', '');
                $row = [
                    'id' => $doc['id'],
                    'key' => $key,
                    'title' => (string) ($doc['title'] ?? ''),
                    'category_id' => data_get($doc, 'admin_doc_category_id'),
                ];

                return $this->moveItemFor($row, $newPrefix.substr($key, strlen($oldPrefix)));
            })
            ->values()
            ->all();

        $folder->update(['status' => 'moving']);

        MoveProjectFilesJob::dispatch((int) $this->id, $items, $folder->id, $folderUpdate);
        Toaster::success('Perubahan folder diproses di background');
    }

    /**
     * Hapus satu file: record BEPM dulu, baru objek MinIO — record yatim lebih
     * buruk daripada objek yatim (objek yatim ditangani pembersihan manual).
     *
     * @param  array<string, mixed>  $row
     */
    protected function deleteOne(array $row): bool
    {
        $result = app(ProjectWriter::class)->deleteDoc((int) $row['id']);

        if (! $result['ok'] && $result['status'] !== 404) {
            Log::error('file-manager: hapus record BEPM gagal', ['doc_id' => $row['id'], 'result' => $result]);

            return false;
        }

        if (! $row['legacy']) {
            try {
                app(ProjectFileStorage::class)->deleteObject($row['key']);
            } catch (\Throwable $e) {
                Log::warning('file-manager: hapus objek gagal — objek yatim', ['key' => $row['key'], 'error' => $e->getMessage()]);
            }
        }

        return true;
    }

    protected function categoryOf(string $ext): string
    {
        foreach (self::CATEGORY_EXTENSIONS as $category => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $category;
            }
        }

        return 'lainnya';
    }

    protected function sizeToBytes(string $size): float
    {
        preg_match('/([\d\.]+)\s*(KB|MB|GB|B)/i', $size, $match);

        $value = (float) ($match[1] ?? 0);

        return match (strtoupper($match[2] ?? 'B')) {
            'GB' => $value * 1024 * 1024 * 1024,
            'MB' => $value * 1024 * 1024,
            'KB' => $value * 1024,
            default => $value,
        };
    }
}; ?>

<div>
    @if($forbidden)
        <div class="rounded-2xl border border-zinc-200 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Akses Ditolak</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Anda tidak punya akses ke file project ini.</flux:text>
        </div>
    @else
    <div class="space-y-5" @if($this->hasBusyFolder) wire:poll.5s @endif>

        {{-- ============ BREADCRUMB ============ --}}
        <flux:breadcrumbs>
            @foreach($this->breadcrumbs as $crumb)
                <flux:breadcrumbs.item wire:key="crumb-{{ $crumb['id'] ?? 'root' }}">
                    @if($loop->last)
                        {{ $crumb['name'] }}
                    @else
                        <button type="button" class="cursor-pointer hover:underline"
                            wire:click="openFolder({{ $crumb['id'] !== null ? $crumb['id'] : 'null' }})">
                            {{ $crumb['name'] }}
                        </button>
                    @endif
                </flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>

        {{-- ============ UPLOAD DROP AREA ============ --}}
        @if($this->currentFolder?->status === null || $this->currentFolder === null)
        <div
            x-data="projectFileManagerUploader(@js((int) $this->id), @js((int) config('uploads.project_files.part_size')))"
            class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
        >
            <label
                for="fm-file-input"
                class="relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-200 bg-zinc-50 px-6 py-8 transition hover:border-red-200 hover:bg-red-50/30 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-red-800 dark:hover:bg-red-950/20"
                @dragover.prevent="$el.classList.add('border-red-300','bg-red-50')"
                @dragleave.prevent="$el.classList.remove('border-red-300','bg-red-50')"
                @drop.prevent="
                    $el.classList.remove('border-red-300','bg-red-50');
                    if ($event.dataTransfer.files?.length) addFiles($event.dataTransfer.files);
                "
            >
                <input id="fm-file-input" type="file" multiple class="sr-only"
                    @change="if ($event.target.files?.length) { addFiles($event.target.files); $event.target.value = ''; }">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
                    <flux:icon.cloud-arrow-up class="h-5 w-5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Klik atau drop file di sini</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Upload ke: <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ collect($this->breadcrumbs)->pluck('name')->implode(' / ') }}</span>
                </p>
            </label>

            {{-- Progress per file --}}
            <div class="mt-3 space-y-2" x-show="uploads.length > 0" x-cloak>
                <template x-for="item in uploads" :key="item.id">
                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex items-center justify-between gap-3">
                            <span class="min-w-0 truncate text-xs font-medium text-zinc-800 dark:text-zinc-200" x-text="item.name"></span>
                            <div class="flex shrink-0 items-center gap-2 text-[11px]">
                                <span x-show="item.status === 'uploading'" class="text-zinc-500" x-text="item.progress + '%'"></span>
                                <span x-show="item.status === 'done'" class="text-emerald-600">Selesai</span>
                                <span x-show="item.status === 'error'" class="text-red-600" x-text="item.message || 'Gagal'"></span>
                                <button x-show="item.status === 'uploading'" type="button"
                                    class="text-red-600 hover:underline" @click="cancel(item.id)">Batal</button>
                                <button x-show="item.status !== 'uploading'" type="button"
                                    class="text-zinc-500 hover:underline" @click="dismiss(item.id)">Tutup</button>
                            </div>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full transition-all"
                                :class="item.status === 'error' ? 'bg-red-500' : (item.status === 'done' ? 'bg-emerald-500' : 'bg-red-600')"
                                :style="`width: ${item.progress}%`"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        @else
        <flux:callout icon="arrow-path" variant="warning">
            Folder ini sedang diproses (rename/move/hapus) — operasi lain dikunci sementara.
        </flux:callout>
        @endif

        {{-- ============ TABLE CARD ============ --}}
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            {{-- Toolbar: search + filter kiri, judul kanan --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="w-64">
                        <flux:input size="sm" wire:model.live.debounce.300ms="search"
                            placeholder="Cari nama file..." icon="magnifying-glass" clearable />
                    </div>
                    <div class="w-40">
                        <flux:select size="sm" wire:model.live="categoryFilter">
                            <flux:select.option value="all">Semua kategori</flux:select.option>
                            <flux:select.option value="dokumen">Dokumen</flux:select.option>
                            <flux:select.option value="gambar">Gambar</flux:select.option>
                            <flux:select.option value="spreadsheet">Spreadsheet</flux:select.option>
                            <flux:select.option value="arsip">Arsip</flux:select.option>
                            <flux:select.option value="lainnya">Lainnya</flux:select.option>
                        </flux:select>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <flux:modal.trigger name="new-folder-modal">
                        <flux:button size="sm" icon="folder-plus" variant="outline">Folder baru</flux:button>
                    </flux:modal.trigger>
                    <flux:heading size="lg" class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $this->currentFolder?->name ?? 'Semua File' }}
                    </flux:heading>
                </div>
            </div>

            {{-- Bulk bar --}}
            @if($selected !== [])
                <div class="flex items-center justify-between gap-3 border-b border-red-100 bg-red-50/60 px-5 py-2.5 dark:border-red-900/40 dark:bg-red-950/20">
                    <span class="text-sm font-medium text-red-700 dark:text-red-300">{{ count($selected) }} file terpilih</span>
                    <div class="flex items-center gap-2">
                        <flux:modal.trigger name="move-selected-modal">
                            <flux:button size="xs" icon="arrow-right-circle" variant="outline">Pindahkan</flux:button>
                        </flux:modal.trigger>
                        <flux:button size="xs" icon="trash" variant="danger" wire:click="deleteSelected"
                            wire:confirm="Hapus {{ count($selected) }} file terpilih? Tindakan ini permanen.">
                            Hapus
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="w-10 px-5 py-3">
                                <input type="checkbox"
                                    class="rounded border-zinc-300 text-red-600 focus:ring-red-500 dark:border-zinc-600 dark:bg-zinc-800"
                                    @change="$wire.set('selected', $event.target.checked ? @js(collect($this->files)->pluck('id')->map(fn ($i) => (string) $i)->all()) : [])"
                                    @checked($this->files !== [] && count($selected) === count($this->files))>
                            </th>
                            <th class="px-2 py-3">
                                <button type="button" class="flex cursor-pointer items-center gap-1 uppercase" wire:click="sortColumn('name')">
                                    Nama
                                    @if($sortBy === 'name')<flux:icon name="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-3 w-3" />@endif
                                </button>
                            </th>
                            <th class="px-2 py-3">
                                <button type="button" class="flex cursor-pointer items-center gap-1 uppercase" wire:click="sortColumn('date')">
                                    Terakhir diubah
                                    @if($sortBy === 'date')<flux:icon name="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-3 w-3" />@endif
                                </button>
                            </th>
                            <th class="px-2 py-3">Kategori</th>
                            <th class="px-2 py-3">
                                <button type="button" class="flex cursor-pointer items-center gap-1 uppercase" wire:click="sortColumn('size')">
                                    Ukuran
                                    @if($sortBy === 'size')<flux:icon name="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-3 w-3" />@endif
                                </button>
                            </th>
                            <th class="w-14 px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Folder rows --}}
                        @foreach($this->folders as $folder)
                            <tr wire:key="folder-{{ $folder->id }}"
                                class="border-b border-zinc-100 transition hover:bg-zinc-50/70 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-2.5"></td>
                                <td class="px-2 py-2.5">
                                    <button type="button" wire:click="openFolder({{ $folder->id }})"
                                        class="flex cursor-pointer items-center gap-2.5 font-medium text-zinc-900 hover:text-red-600 dark:text-zinc-100 dark:hover:text-red-400">
                                        <flux:icon.folder class="h-5 w-5 text-amber-500" variant="solid" />
                                        <span class="truncate">{{ $folder->name }}</span>
                                        @if($folder->status !== null)
                                            <flux:badge size="sm" color="amber" icon="arrow-path">Diproses…</flux:badge>
                                        @endif
                                    </button>
                                </td>
                                <td class="px-2 py-2.5 text-zinc-500 dark:text-zinc-400">
                                    {{ $folder->updated_at?->locale('id')->translatedFormat('d M Y') }}
                                </td>
                                <td class="px-2 py-2.5 text-zinc-500 dark:text-zinc-400">Folder</td>
                                <td class="px-2 py-2.5 text-zinc-500 dark:text-zinc-400">—</td>
                                <td class="px-5 py-2.5 text-right">
                                    @if($folder->status === null)
                                        <flux:dropdown position="bottom" align="end" wire:key="folder-menu-{{ $folder->id }}">
                                            <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                            <flux:navmenu>
                                                <flux:navmenu.item icon="pencil-square" wire:click="startRenameFolder({{ $folder->id }})">Rename</flux:navmenu.item>
                                                <flux:navmenu.item icon="arrow-right-circle" wire:click="startMoveFolder({{ $folder->id }})">Pindahkan</flux:navmenu.item>
                                                <flux:navmenu.separator />
                                                <flux:navmenu.item icon="trash" variant="danger" wire:click="confirmDeleteFolder({{ $folder->id }})">Hapus</flux:navmenu.item>
                                            </flux:navmenu>
                                        </flux:dropdown>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        {{-- File rows --}}
                        @forelse($this->files as $file)
                            @php
                                $iconStyles = match($file['category']) {
                                    'dokumen' => ['icon' => 'document-text', 'class' => 'text-red-500'],
                                    'gambar' => ['icon' => 'photo', 'class' => 'text-purple-500'],
                                    'spreadsheet' => ['icon' => 'table-cells', 'class' => 'text-emerald-500'],
                                    'arsip' => ['icon' => 'archive-box', 'class' => 'text-amber-600'],
                                    default => ['icon' => 'document', 'class' => 'text-zinc-400'],
                                };
                            @endphp
                            <tr wire:key="file-{{ $file['id'] }}"
                                class="border-b border-zinc-100 transition hover:bg-zinc-50/70 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-2.5">
                                    <input type="checkbox" value="{{ $file['id'] }}" wire:model.live="selected"
                                        class="rounded border-zinc-300 text-red-600 focus:ring-red-500 dark:border-zinc-600 dark:bg-zinc-800">
                                </td>
                                <td class="px-2 py-2.5">
                                    <div class="flex items-center gap-2.5 font-medium text-zinc-900 dark:text-zinc-100">
                                        <flux:icon name="{{ $iconStyles['icon'] }}" class="h-5 w-5 shrink-0 {{ $iconStyles['class'] }}" />
                                        <span class="max-w-100 truncate" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                        @if($file['legacy'])
                                            <flux:badge size="sm" color="zinc">lama</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2 py-2.5 text-zinc-500 dark:text-zinc-400">
                                    {{ $file['created_at'] !== '' ? \Carbon\Carbon::parse($file['created_at'])->locale('id')->translatedFormat('d M Y') : '—' }}
                                </td>
                                <td class="px-2 py-2.5">
                                    <span class="text-zinc-600 capitalize dark:text-zinc-300">{{ $file['category'] }}</span>
                                </td>
                                <td class="px-2 py-2.5 text-zinc-500 dark:text-zinc-400">{{ $file['size'] !== '' ? $file['size'] : '—' }}</td>
                                <td class="px-5 py-2.5 text-right">
                                    <flux:dropdown position="bottom" align="end" wire:key="file-menu-{{ $file['id'] }}">
                                        <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                        <flux:navmenu>
                                            <flux:navmenu.item icon="eye" wire:click="openPreview({{ $file['id'] }})">Preview</flux:navmenu.item>
                                            @unless($file['legacy'])
                                                <flux:navmenu.item icon="pencil-square" wire:click="startRenameDoc({{ $file['id'] }})">Rename</flux:navmenu.item>
                                            @endunless
                                            <flux:navmenu.separator />
                                            <flux:navmenu.item icon="trash" variant="danger" wire:click="confirmDeleteDoc({{ $file['id'] }})">Hapus</flux:navmenu.item>
                                        </flux:navmenu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            @if($this->folders === [])
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <flux:icon.document class="h-6 w-6 text-zinc-400" />
                                        </div>
                                        <flux:heading size="sm" class="mt-3 text-zinc-900 dark:text-zinc-100">
                                            {{ $search !== '' ? 'Tidak ada hasil' : 'Folder ini kosong' }}
                                        </flux:heading>
                                        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $search !== '' ? 'Coba kata kunci lain' : 'Drop file di atas untuk mengupload' }}
                                        </flux:text>
                                    </td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ MODALS ============ --}}

    <flux:modal name="new-folder-modal" class="max-w-md">
        <form wire:submit="createFolder" class="space-y-5">
            <flux:heading size="lg">Folder baru</flux:heading>
            <flux:field>
                <flux:label>Nama folder</flux:label>
                <flux:input wire:model="newFolderName" placeholder="cth. Dokumen Kontrak" />
                @error('newFolderName')<flux:error message="{{ $message }}" />@enderror
            </flux:field>
            <div class="flex gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost" class="flex-1">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" class="flex-1">Buat</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="rename-folder-modal" class="max-w-md">
        <form wire:submit="renameFolder" class="space-y-5">
            <flux:heading size="lg">Rename folder</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Seluruh file di dalamnya dipindahkan di background — folder terkunci selama proses.
            </flux:text>
            <flux:field>
                <flux:label>Nama baru</flux:label>
                <flux:input wire:model="renameFolderName" />
                @error('renameFolderName')<flux:error message="{{ $message }}" />@enderror
            </flux:field>
            <div class="flex gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost" class="flex-1">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" class="flex-1">Rename</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="move-folder-modal" class="max-w-md">
        <form wire:submit="moveFolder" class="space-y-5">
            <flux:heading size="lg">Pindahkan folder</flux:heading>
            <flux:field>
                <flux:label>Folder tujuan</flux:label>
                <flux:select wire:model="moveFolderTargetId">
                    @foreach($this->folderOptions($movingFolderId) as $option)
                        <flux:select.option value="{{ $option['id'] }}" wire:key="mf-{{ $option['id'] ?? 'root' }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <div class="flex gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost" class="flex-1">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" class="flex-1">Pindahkan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="move-selected-modal" class="max-w-md">
        <form wire:submit="moveSelected" class="space-y-5">
            <flux:heading size="lg">Pindahkan {{ count($selected) }} file</flux:heading>
            <flux:field>
                <flux:label>Folder tujuan</flux:label>
                <flux:select wire:model="moveSelectedTargetId">
                    @foreach($this->folderOptions() as $option)
                        <flux:select.option value="{{ $option['id'] }}" wire:key="ms-{{ $option['id'] ?? 'root' }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <div class="flex gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost" class="flex-1">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" class="flex-1">Pindahkan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="rename-file-modal" class="max-w-md">
        <form wire:submit="renameDoc" class="space-y-5">
            <flux:heading size="lg">Rename file</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Ekstensi file dipertahankan; proses berjalan di background.
            </flux:text>
            <flux:field>
                <flux:label>Nama baru (tanpa ekstensi)</flux:label>
                <flux:input wire:model="renameDocName" />
                @error('renameDocName')<flux:error message="{{ $message }}" />@enderror
            </flux:field>
            <div class="flex gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost" class="flex-1">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" class="flex-1">Rename</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-confirm-modal name="delete-file-modal" confirm="deleteDoc" title="Hapus File?">
        File <span class="font-medium text-zinc-800 dark:text-zinc-200">"{{ $deletingDocName }}"</span>
        akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
    </x-confirm-modal>

    <x-confirm-modal name="delete-folder-modal" confirm="deleteFolder" title="Hapus Folder Beserta Isinya?">
        Folder ini berisi <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $deletingFolderFileCount }} file</span>
        (termasuk subfolder). Semuanya akan dihapus permanen di background.
    </x-confirm-modal>

    <flux:modal name="file-preview-modal" class="!max-w-[900px] lg:min-w-[900px] md:min-w-[600px]">
        @php
            $isPreviewImage = in_array($previewExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
            $isPreviewPdf = $previewExt === 'pdf';
        @endphp
        <div class="space-y-5">
            <flux:heading size="lg" class="truncate pe-8 font-semibold text-zinc-900 dark:text-zinc-100">{{ $previewName }}</flux:heading>

            @if($isPreviewPdf)
                <iframe src="{{ $previewUrl }}" class="h-150 w-full rounded-xl border border-zinc-200 dark:border-zinc-700" frameborder="0"></iframe>
            @elseif($isPreviewImage)
                <div class="flex min-h-75 items-center justify-center rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <img src="{{ $previewUrl }}" alt="{{ $previewName }}" class="max-h-140 max-w-full rounded-lg object-contain shadow-sm" loading="lazy" />
                </div>
            @elseif($previewUrl !== '')
                <div class="flex h-60 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:icon.document class="h-8 w-8 text-zinc-400" />
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Preview tidak tersedia untuk format .{{ strtoupper($previewExt) }}</p>
                </div>
            @endif

            @if($previewUrl !== '')
                <div class="flex justify-end">
                    <flux:button :href="$previewUrl" target="_blank" variant="primary" size="sm" icon="arrow-down-tray">
                        Download
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
    @endif
</div>

