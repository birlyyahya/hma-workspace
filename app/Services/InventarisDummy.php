<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;

/**
 * Penyedia data + aksi dummy modul Inventaris (fase demo, belum tersambung API).
 *
 * Berbeda dari CaDummy yang stateless, service ini menyimpan state di SESSION
 * agar seluruh aksi (generate batch, assign SN, ubah status, dsb.) benar-benar
 * tersimpan dan konsisten antar-halaman selama sesi demo. Panggil reset() untuk
 * menyetel ulang ke data awal.
 *
 * Model data (semua di session):
 * - projects : master project (id, name, company).
 * - spekteks : master spektek + sub_items (id, name, unit, qty_total).
 * - receipts : batch penerimaan (goods receipt) — 1 batch = 1 sub_item + qty.
 * - units    : unit fisik, didenormalisasi (bawa project/spektek/sub_item/receipt)
 *              agar mudah diquery. id unit = receipt_id * 100 + nomor urut.
 *
 * Angka progres (generated / sn_assigned) SELALU dihitung ulang dari units,
 * tidak disimpan, sehingga otomatis sinkron setiap ada mutasi.
 *
 * Status lifecycle unit: empty → sn_assigned → in_transit → delivered → installed,
 * plus jalur damaged. Uang/kuantitas selalu integer.
 */
class InventarisDummy
{
    private const SESSION_KEY = 'inventaris.dummy.state';

    /**
     * Urutan lifecycle normal (di luar jalur damaged).
     *
     * @var array<int, string>
     */
    private const LIFECYCLE = ['empty', 'sn_assigned', 'in_transit', 'delivered', 'installed'];

    /**
     * Master satker/tujuan pengadaan untuk dropdown alokasi.
     *
     * @return array<int, string>
     */
    public static function satkers(): array
    {
        return [
            'Satker A · Dinas Pendidikan',
            'Satker B · Dinas Kesehatan',
            'Satker C · Dinas PU',
            'Satker D · Dinas Sosial',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'empty' => 'Belum ada SN',
            'sn_assigned' => 'SN Terpasang',
            'in_transit' => 'Dalam Pengiriman',
            'delivered' => 'Diterima di Site',
            'installed' => 'Terpasang',
            'damaged' => 'Rusak',
            default => ucfirst($status),
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'empty' => 'zinc',
            'sn_assigned' => 'blue',
            'in_transit' => 'amber',
            'delivered' => 'green',
            'installed' => 'emerald',
            'damaged' => 'red',
            default => 'zinc',
        };
    }

    /**
     * Status berikutnya pada jalur lifecycle normal (null bila sudah terakhir/empty/damaged).
     */
    public static function nextStatus(string $status): ?string
    {
        $i = array_search($status, self::LIFECYCLE, true);

        if ($i === false || $i === 0 || $i === count(self::LIFECYCLE) - 1) {
            return null;
        }

        return self::LIFECYCLE[$i + 1];
    }

    /**
     * Aksi utama berikutnya untuk tombol besar di detail unit.
     *
     * @return array{label: string, icon: string, status: string}|null
     */
    public static function nextAction(string $status): ?array
    {
        return match ($status) {
            'empty' => ['label' => 'Assign SN', 'icon' => 'qr-code', 'status' => 'sn_assigned'],
            'sn_assigned' => ['label' => 'Kirim ke Lokasi', 'icon' => 'truck', 'status' => 'in_transit'],
            'in_transit' => ['label' => 'Konfirmasi Diterima', 'icon' => 'check-circle', 'status' => 'delivered'],
            'delivered' => ['label' => 'Tandai Terpasang', 'icon' => 'wrench-screwdriver', 'status' => 'installed'],
            default => null,
        };
    }

    // =========================================================================
    // STATE
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        if (! Session::has(self::SESSION_KEY)) {
            Session::put(self::SESSION_KEY, $this->seed());
        }

        return Session::get(self::SESSION_KEY);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function save(array $state): void
    {
        Session::put(self::SESSION_KEY, $state);
    }

    /**
     * Kembalikan state demo ke data awal.
     */
    public function reset(): void
    {
        Session::put(self::SESSION_KEY, $this->seed());
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Daftar project + ringkasan inventaris (dihitung dari units).
     *
     * @return array<int, array<string, mixed>>
     */
    public function projects(): array
    {
        $state = $this->state();

        return array_values(array_map(
            fn (array $p) => [...$p, ...$this->projectSummary($state, $p['id'])],
            $state['projects'],
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function project(int $projectId): ?array
    {
        $state = $this->state();
        $p = $state['projects'][$projectId] ?? null;

        return $p === null ? null : [...$p, ...$this->projectSummary($state, $projectId)];
    }

    /**
     * Spektek sebuah project + progres tiap sub-item & agregat spektek.
     *
     * @return array<int, array<string, mixed>>
     */
    public function spekteksForProject(int $projectId): array
    {
        $state = $this->state();

        return collect($state['spekteks'])
            ->where('project_id', $projectId)
            ->map(fn (array $s) => $this->decorateSpektek($state, $s))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function spektek(int $spektekId): ?array
    {
        $state = $this->state();
        $s = $state['spekteks'][$spektekId] ?? null;

        return $s === null ? null : $this->decorateSpektek($state, $s);
    }

    /**
     * Riwayat batch sebuah spektek (terbaru dulu), dengan sn_filled dihitung ulang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function receiptsForSpektek(int $spektekId): array
    {
        $state = $this->state();

        return collect($state['receipts'])
            ->where('spektek_id', $spektekId)
            ->map(fn (array $r) => [...$r, 'sn_filled' => $this->snCount($state, fn ($u) => $u['receipt_id'] === $r['id'])])
            ->sortByDesc('code')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function receipt(int $receiptId): ?array
    {
        $state = $this->state();
        $r = $state['receipts'][$receiptId] ?? null;

        if ($r === null) {
            return null;
        }

        return [...$r, 'sn_filled' => $this->snCount($state, fn ($u) => $u['receipt_id'] === $receiptId)];
    }

    /**
     * Unit dalam sebuah batch, terurut nomor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function unitsForReceipt(int $receiptId): array
    {
        return collect($this->state()['units'])
            ->where('receipt_id', $receiptId)
            ->sortBy('no')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function unit(int $unitId): ?array
    {
        return $this->state()['units'][$unitId] ?? null;
    }

    /**
     * Cari unit berdasarkan SN atau kode unit (untuk scanner & search global).
     *
     * @return array<string, mixed>|null
     */
    public function findBySnOrCode(string $needle): ?array
    {
        $needle = trim($needle);

        if ($needle === '') {
            return null;
        }

        return collect($this->state()['units'])
            ->first(fn (array $u) => strcasecmp($u['code'], $needle) === 0
                || ($u['sn'] !== null && strcasecmp($u['sn'], $needle) === 0));
    }

    /**
     * Timeline pergerakan unit (terbaru dulu), disintesis dari status saat ini.
     *
     * @param  array<string, mixed>  $unit
     * @return array<int, array<string, mixed>>
     */
    public function timelineFor(array $unit): array
    {
        $site = match ($unit['project_id']) {
            102 => 'Eka Prasetyo',
            104 => 'Fajar Nugroho',
            default => 'Andi Wijaya',
        };

        $steps = [[
            'status' => 'empty', 'label' => 'Unit Dibuat', 'photo' => false,
            'desc' => "Unit dibuat dari batch {$unit['receipt_code']}.",
            'by' => 'Sistem', 'when' => $unit['received_at'],
        ]];

        if ($unit['sn'] !== null) {
            $steps[] = ['status' => 'sn_assigned', 'label' => 'SN Terpasang', 'photo' => true,
                'desc' => "SN {$unit['sn']} dicatat untuk unit ini.", 'by' => $unit['receipt_by'], 'when' => $unit['received_at']];
        }

        if (in_array($unit['status'], ['in_transit', 'delivered', 'installed'], true)) {
            $steps[] = ['status' => 'in_transit', 'label' => 'Dalam Pengiriman', 'photo' => false,
                'desc' => 'Dikirim ke '.($unit['destination'] ?? 'lokasi tujuan').'.', 'by' => $unit['receipt_by'], 'when' => $unit['received_at']];
        }

        if (in_array($unit['status'], ['delivered', 'installed'], true)) {
            $steps[] = ['status' => 'delivered', 'label' => 'Diterima di Site', 'photo' => true,
                'desc' => 'Diterima oleh tim site, kondisi baik.', 'by' => $site, 'when' => $unit['received_at']];
        }

        if ($unit['status'] === 'installed') {
            $steps[] = ['status' => 'installed', 'label' => 'Terpasang', 'photo' => true,
                'desc' => 'Dipasang dan diaktifkan di lokasi.', 'by' => $site, 'when' => $unit['received_at']];
        }

        if ($unit['status'] === 'damaged') {
            $steps[] = ['status' => 'damaged', 'label' => 'Dilaporkan Rusak', 'photo' => true,
                'desc' => 'Unit ditemukan rusak, dipindahkan ke '.($unit['destination'] ?? 'gudang').'.', 'by' => $site, 'when' => $unit['received_at']];
        }

        return array_reverse($steps);
    }

    /**
     * Pratinjau import SN (tanpa mutasi): pasangkan tiap SN ke unit kosong
     * berurutan, tandai duplikat & kelebihan.
     *
     * @param  array<int, string>  $sns
     * @return array<int, array{row: int, sn: string, unit: ?string, state: string, note: string}>
     */
    public function previewImport(int $receiptId, array $sns): array
    {
        $state = $this->state();
        $empties = collect($this->unitsForReceipt($receiptId))->where('status', 'empty')->values();
        $existing = collect($state['units'])->pluck('sn')->filter()->map(fn ($s) => strtolower($s))->all();

        $rows = [];
        $slot = 0;
        $usedInBatch = [];

        foreach ($sns as $i => $raw) {
            $sn = trim($raw);

            if ($sn === '') {
                continue;
            }

            $key = strtolower($sn);
            $row = ['row' => $i + 1, 'sn' => $sn, 'unit' => null, 'state' => 'ok', 'note' => 'OK'];

            if (in_array($key, $existing, true) || in_array($key, $usedInBatch, true)) {
                $row['state'] = 'duplicate';
                $row['note'] = 'Duplikat (SN sudah dipakai)';
            } elseif ($slot >= $empties->count()) {
                $row['state'] = 'overflow';
                $row['note'] = 'Unit kosong habis';
            } else {
                $row['unit'] = $empties[$slot]['code'];
                $usedInBatch[] = $key;
                $slot++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Unit satu batch dikelompokkan per satker/tujuan. Grup "Belum dialokasikan"
     * (destination null) selalu ditaruh terakhir.
     *
     * @return array<int, array{destination: ?string, count: int, units: array<int, array<string, mixed>>, status_counts: array<int, array{label: string, color: string, count: int}>, can_advance: bool}>
     */
    public function unitsGroupedByDestination(int $receiptId): array
    {
        $grouped = collect($this->unitsForReceipt($receiptId))
            ->groupBy(fn (array $u) => $u['destination'] ?? '');

        $result = [];

        foreach ($grouped as $dest => $units) {
            if ($dest !== '') {
                $result[] = $this->buildGroup($dest, $units->all());
            }
        }

        usort($result, fn ($a, $b) => strcmp((string) $a['destination'], (string) $b['destination']));

        if ($grouped->has('')) {
            $result[] = $this->buildGroup(null, $grouped->get('')->all());
        }

        return $result;
    }

    // =========================================================================
    // WRITE (fitur)
    // =========================================================================

    /**
     * Generate batch baru untuk sebuah sub-item: buat goods receipt + N unit kosong.
     * Qty di-clamp ke sisa spektek. Mengembalikan id receipt baru (0 bila tak ada sisa).
     */
    public function generate(int $spektekId, int $subItemId, int $qty): int
    {
        $state = $this->state();
        $spektek = $state['spekteks'][$spektekId] ?? null;

        if ($spektek === null) {
            return 0;
        }

        $subItem = collect($spektek['sub_items'])->firstWhere('id', $subItemId);

        if ($subItem === null) {
            return 0;
        }

        $remaining = $subItem['qty_total'] - $this->generatedCount($state, $subItemId);
        $qty = min(max($qty, 1), $remaining);

        if ($qty < 1) {
            return 0;
        }

        $seq = ++$state['seq']['receipt'];
        $receiptId = 600 + $seq;
        $code = sprintf('GR-2026-%04d', $seq);

        $state['receipts'][$receiptId] = [
            'id' => $receiptId,
            'code' => $code,
            'project_id' => $spektek['project_id'],
            'spektek_id' => $spektekId,
            'spektek_name' => $spektek['name'],
            'sub_item_id' => $subItemId,
            'sub_item' => $subItem['name'],
            'qty' => $qty,
            'received_at' => now()->translatedFormat('j M Y'),
            'by' => 'Demo User',
        ];

        $prefix = str_replace('GR-2026-', 'INV-', $code);

        for ($no = 1; $no <= $qty; $no++) {
            $id = $receiptId * 100 + $no;
            $state['units'][$id] = [
                'id' => $id, 'no' => $no, 'code' => sprintf('%s-%03d', $prefix, $no),
                'receipt_id' => $receiptId, 'receipt_code' => $code, 'receipt_by' => 'Demo User',
                'received_at' => $state['receipts'][$receiptId]['received_at'],
                'spektek_id' => $spektekId, 'spektek_name' => $spektek['name'],
                'sub_item_id' => $subItemId, 'item' => $subItem['name'],
                'project_id' => $spektek['project_id'],
                'sn' => null, 'status' => 'empty', 'destination' => null,
            ];
        }

        $this->save($state);

        return $receiptId;
    }

    /**
     * Assign SN ke satu unit kosong. Menolak SN kosong/duplikat.
     *
     * @return array{ok: bool, message: string}
     */
    public function assignSn(int $unitId, string $sn, ?string $destination = null): array
    {
        $state = $this->state();
        $sn = trim($sn);

        if (! isset($state['units'][$unitId])) {
            return ['ok' => false, 'message' => 'Unit tidak ditemukan.'];
        }

        if ($sn === '') {
            return ['ok' => false, 'message' => 'SN tidak boleh kosong.'];
        }

        if ($this->snExists($state, $sn)) {
            return ['ok' => false, 'message' => "SN {$sn} sudah dipakai unit lain."];
        }

        $state['units'][$unitId]['sn'] = $sn;
        $state['units'][$unitId]['status'] = 'sn_assigned';

        if ($destination !== null && trim($destination) !== '') {
            $state['units'][$unitId]['destination'] = trim($destination);
        }

        $this->save($state);

        return ['ok' => true, 'message' => "SN {$sn} tersimpan."];
    }

    /**
     * Assign SN ke unit kosong PERTAMA dalam batch (dipakai mode scan beruntun).
     *
     * @return array{ok: bool, message: string, unit: ?string}
     */
    public function assignNextEmpty(int $receiptId, string $sn): array
    {
        $next = collect($this->unitsForReceipt($receiptId))->firstWhere('status', 'empty');

        if ($next === null) {
            return ['ok' => false, 'message' => 'Semua unit batch ini sudah ber-SN.', 'unit' => null];
        }

        $result = $this->assignSn($next['id'], $sn);

        return [...$result, 'unit' => $result['ok'] ? $next['code'] : null];
    }

    /**
     * Ubah SN unit yang sudah terisi (edit). Duplikat ditolak (kecuali sama dgn miliknya).
     *
     * @return array{ok: bool, message: string}
     */
    public function updateSn(int $unitId, string $sn): array
    {
        $state = $this->state();
        $sn = trim($sn);

        if (! isset($state['units'][$unitId])) {
            return ['ok' => false, 'message' => 'Unit tidak ditemukan.'];
        }

        if ($sn === '') {
            return ['ok' => false, 'message' => 'SN tidak boleh kosong.'];
        }

        if ($this->snExists($state, $sn, exceptUnitId: $unitId)) {
            return ['ok' => false, 'message' => "SN {$sn} sudah dipakai unit lain."];
        }

        $state['units'][$unitId]['sn'] = $sn;

        if ($state['units'][$unitId]['status'] === 'empty') {
            $state['units'][$unitId]['status'] = 'sn_assigned';
        }

        $this->save($state);

        return ['ok' => true, 'message' => 'SN diperbarui.'];
    }

    /**
     * Terapkan import SN: assign ke unit kosong berurutan, lewati duplikat/overflow.
     *
     * @param  array<int, string>  $sns
     * @return array{assigned: int, duplicates: int, overflow: int}
     */
    public function importSn(int $receiptId, array $sns): array
    {
        $summary = ['assigned' => 0, 'duplicates' => 0, 'overflow' => 0];

        foreach ($this->previewImport($receiptId, $sns) as $row) {
            if ($row['state'] === 'ok') {
                $result = $this->assignNextEmpty($receiptId, $row['sn']);
                $summary[$result['ok'] ? 'assigned' : 'duplicates']++;
            } elseif ($row['state'] === 'duplicate') {
                $summary['duplicates']++;
            } else {
                $summary['overflow']++;
            }
        }

        return $summary;
    }

    /**
     * Ubah status unit ke tahap tertentu (advance lifecycle atau set damaged).
     *
     * @return array{ok: bool, message: string}
     */
    public function setStatus(int $unitId, string $status, ?string $destination = null): array
    {
        $state = $this->state();

        if (! isset($state['units'][$unitId])) {
            return ['ok' => false, 'message' => 'Unit tidak ditemukan.'];
        }

        $unit = $state['units'][$unitId];

        if ($status !== 'damaged' && $unit['sn'] === null) {
            return ['ok' => false, 'message' => 'Assign SN terlebih dahulu.'];
        }

        $state['units'][$unitId]['status'] = $status;

        if ($destination !== null && trim($destination) !== '') {
            $state['units'][$unitId]['destination'] = trim($destination);
        }

        $this->save($state);

        return ['ok' => true, 'message' => 'Status: '.self::statusLabel($status).'.'];
    }

    /**
     * Alokasikan sejumlah unit BELUM bersatker ke satu satker (per jumlah).
     * Mengambil unit tersedia berurutan; mengembalikan berapa yang teralokasi.
     */
    public function allocateToSatker(int $receiptId, string $satker, int $count): int
    {
        $satker = trim($satker);

        if ($satker === '' || $count < 1) {
            return 0;
        }

        $state = $this->state();

        $available = collect($state['units'])
            ->where('receipt_id', $receiptId)
            ->whereNull('destination')
            ->sortBy('no')
            ->take($count);

        foreach ($available as $unit) {
            $state['units'][$unit['id']]['destination'] = $satker;
        }

        $this->save($state);

        return $available->count();
    }

    /**
     * Set satker untuk sekumpulan unit tertentu (pilih SN manual / bulk).
     *
     * @param  array<int, int>  $unitIds
     */
    public function assignUnitsToSatker(array $unitIds, string $satker): int
    {
        $satker = trim($satker);

        if ($satker === '') {
            return 0;
        }

        $state = $this->state();
        $n = 0;

        foreach ($unitIds as $id) {
            if (isset($state['units'][$id])) {
                $state['units'][$id]['destination'] = $satker;
                $n++;
            }
        }

        $this->save($state);

        return $n;
    }

    /**
     * Set status untuk banyak unit sekaligus. Unit tanpa SN dilewati kecuali
     * status damaged.
     *
     * @param  array<int, int>  $unitIds
     * @return array{applied: int, skipped: int}
     */
    public function bulkSetStatus(array $unitIds, string $status): array
    {
        $state = $this->state();
        $applied = 0;
        $skipped = 0;

        foreach ($unitIds as $id) {
            $unit = $state['units'][$id] ?? null;

            if ($unit === null) {
                continue;
            }

            if ($status !== 'damaged' && $unit['sn'] === null) {
                $skipped++;

                continue;
            }

            $state['units'][$id]['status'] = $status;
            $applied++;
        }

        $this->save($state);

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * Majukan status banyak unit satu tahap pada jalur lifecycle. Unit yang
     * sudah di ujung / belum ber-SN / rusak dilewati.
     *
     * @param  array<int, int>  $unitIds
     * @return array{moved: int, total: int}
     */
    public function bulkAdvance(array $unitIds): array
    {
        $state = $this->state();
        $moved = 0;

        foreach ($unitIds as $id) {
            $unit = $state['units'][$id] ?? null;

            if ($unit === null) {
                continue;
            }

            $next = self::nextStatus($unit['status']);

            if ($next === null) {
                continue;
            }

            $state['units'][$id]['status'] = $next;
            $moved++;
        }

        $this->save($state);

        return ['moved' => $moved, 'total' => \count($unitIds)];
    }

    /**
     * Majukan status seluruh unit dalam satu grup satker.
     *
     * @return array{moved: int, total: int}
     */
    public function advanceGroup(int $receiptId, string $destination): array
    {
        $ids = collect($this->unitsForReceipt($receiptId))
            ->where('destination', $destination)
            ->pluck('id')
            ->all();

        return $this->bulkAdvance($ids);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @return array{destination: ?string, count: int, units: array<int, array<string, mixed>>, status_counts: array<int, array{label: string, color: string, count: int}>, can_advance: bool}
     */
    private function buildGroup(?string $destination, array $units): array
    {
        $statusCounts = collect($units)
            ->countBy('status')
            ->map(fn (int $count, string $status) => [
                'label' => self::statusLabel($status),
                'color' => self::statusColor($status),
                'count' => $count,
            ])
            ->values()
            ->all();

        return [
            'destination' => $destination,
            'count' => \count($units),
            'units' => $units,
            'status_counts' => $statusCounts,
            'can_advance' => collect($units)->contains(fn (array $u) => self::nextStatus($u['status']) !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $spektek
     * @return array<string, mixed>
     */
    private function decorateSpektek(array $state, array $spektek): array
    {
        $subItems = array_map(function (array $sub) use ($state) {
            $generated = $this->generatedCount($state, $sub['id']);
            $sn = $this->snCount($state, fn ($u) => $u['sub_item_id'] === $sub['id']);

            return [...$sub, 'generated' => $generated, 'sn_assigned' => $sn];
        }, $spektek['sub_items']);

        return [...$spektek, 'sub_items' => $subItems];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{spektek_count: int, total_units: int, generated_units: int, sn_units: int, status_label: string, status_color: string}
     */
    private function projectSummary(array $state, int $projectId): array
    {
        $spekteks = collect($state['spekteks'])->where('project_id', $projectId);
        $total = $spekteks->sum(fn ($s) => collect($s['sub_items'])->sum('qty_total'));

        $units = collect($state['units'])->where('project_id', $projectId);
        $generated = $units->count();
        $sn = $units->whereNotNull('sn')->count();
        $damaged = $units->where('status', 'damaged')->count();

        if ($generated === 0) {
            [$label, $color] = ['Belum mulai', 'zinc'];
        } elseif ($sn < $total) {
            [$label, $color] = ['Batch belum lengkap', 'amber'];
        } elseif ($damaged > 0) {
            [$label, $color] = [$damaged.' unit rusak', 'red'];
        } else {
            [$label, $color] = ['Lengkap', 'emerald'];
        }

        return [
            'spektek_count' => $spekteks->count(),
            'total_units' => $total,
            'generated_units' => $generated,
            'sn_units' => $sn,
            'status_label' => $label,
            'status_color' => $color,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function generatedCount(array $state, int $subItemId): int
    {
        return collect($state['units'])->where('sub_item_id', $subItemId)->count();
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  callable(array<string, mixed>): bool  $filter
     */
    private function snCount(array $state, callable $filter): int
    {
        return collect($state['units'])->filter($filter)->whereNotNull('sn')->count();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function snExists(array $state, string $sn, ?int $exceptUnitId = null): bool
    {
        return collect($state['units'])
            ->when($exceptUnitId !== null, fn ($c) => $c->except([$exceptUnitId]))
            ->contains(fn ($u) => $u['sn'] !== null && strcasecmp($u['sn'], $sn) === 0);
    }

    // =========================================================================
    // SEED
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function seed(): array
    {
        $projects = [
            101 => ['id' => 101, 'name' => 'Pembangunan Jaringan FO Cikarang', 'company' => 'PT Telkom Infra', 'last_activity' => '9 Jul 2026'],
            102 => ['id' => 102, 'name' => 'Instalasi CCTV Kawasan MM2100', 'company' => 'PT Bekasi Fajar', 'last_activity' => '7 Jul 2026'],
            103 => ['id' => 103, 'name' => 'Upgrade Server Room Gedung B', 'company' => 'PT Hana Tekindo', 'last_activity' => '1 Jul 2026'],
            104 => ['id' => 104, 'name' => 'Renovasi Jaringan Listrik Gudang C', 'company' => 'PT Sinar Abadi', 'last_activity' => '5 Jul 2026'],
        ];

        $spekteks = [
            11 => ['id' => 11, 'project_id' => 101, 'name' => 'Kabel Fiber Optic 48 Core', 'composite' => false, 'sub_items' => [
                ['id' => 111, 'name' => 'Kabel FO 48 Core (haspel 2km)', 'unit' => 'haspel', 'qty_total' => 40],
            ]],
            12 => ['id' => 12, 'project_id' => 101, 'name' => 'Perangkat OLT + Aksesoris', 'composite' => true, 'sub_items' => [
                ['id' => 121, 'name' => 'OLT ZTE C320', 'unit' => 'unit', 'qty_total' => 8],
                ['id' => 122, 'name' => 'Modul Uplink 10G', 'unit' => 'pcs', 'qty_total' => 16],
                ['id' => 123, 'name' => 'Patchcord SC/UPC 3m', 'unit' => 'pcs', 'qty_total' => 32],
            ]],
            13 => ['id' => 13, 'project_id' => 101, 'name' => 'ODP Pole 16 Port', 'composite' => false, 'sub_items' => [
                ['id' => 131, 'name' => 'ODP Pole 16 Port', 'unit' => 'unit', 'qty_total' => 24],
            ]],
            21 => ['id' => 21, 'project_id' => 102, 'name' => 'Kamera Dome IP 4MP', 'composite' => false, 'sub_items' => [
                ['id' => 211, 'name' => 'Kamera Dome IP 4MP', 'unit' => 'unit', 'qty_total' => 30],
            ]],
            22 => ['id' => 22, 'project_id' => 102, 'name' => 'NVR 16 Channel + HDD', 'composite' => false, 'sub_items' => [
                ['id' => 221, 'name' => 'NVR 16 Channel + HDD', 'unit' => 'unit', 'qty_total' => 15],
            ]],
            31 => ['id' => 31, 'project_id' => 103, 'name' => 'Rack Server 42U', 'composite' => false, 'sub_items' => [
                ['id' => 311, 'name' => 'Rack Server 42U', 'unit' => 'unit', 'qty_total' => 6],
            ]],
            32 => ['id' => 32, 'project_id' => 103, 'name' => 'UPS 3000VA', 'composite' => false, 'sub_items' => [
                ['id' => 321, 'name' => 'UPS 3000VA', 'unit' => 'unit', 'qty_total' => 12],
            ]],
            41 => ['id' => 41, 'project_id' => 104, 'name' => 'Panel Listrik MDP', 'composite' => false, 'sub_items' => [
                ['id' => 411, 'name' => 'Panel Listrik MDP', 'unit' => 'unit', 'qty_total' => 10],
            ]],
        ];

        // Batch 520 (Kamera, 30 unit) dipakai untuk memperagakan distribusi satker:
        // 10 → Satker A (terpasang), 10 → Satker B (diterima), 10 belum dialokasikan.
        $dist520 = [];
        for ($n = 1; $n <= 10; $n++) {
            $dist520[$n] = ['status' => 'installed', 'destination' => 'Satker A · Dinas Pendidikan'];
        }
        for ($n = 11; $n <= 20; $n++) {
            $dist520[$n] = ['status' => 'delivered', 'destination' => 'Satker B · Dinas Kesehatan'];
        }

        $receiptSeed = [
            ['id' => 480, 'code' => 'GR-2026-0001', 'spektek_id' => 11, 'sub_item_id' => 111, 'qty' => 40, 'sn_filled' => 36, 'received_at' => '1 Jun 2026', 'by' => 'Andi Wijaya', 'base' => 'installed', 'overrides' => []],
            ['id' => 498, 'code' => 'GR-2026-0002', 'spektek_id' => 12, 'sub_item_id' => 121, 'qty' => 8, 'sn_filled' => 8, 'received_at' => '15 Jun 2026', 'by' => 'Budi Santoso', 'base' => 'installed', 'overrides' => []],
            ['id' => 500, 'code' => 'GR-2026-0005', 'spektek_id' => 12, 'sub_item_id' => 122, 'qty' => 8, 'sn_filled' => 8, 'received_at' => '28 Jun 2026', 'by' => 'Budi Santoso', 'base' => 'delivered', 'overrides' => []],
            ['id' => 501, 'code' => 'GR-2026-0007', 'spektek_id' => 12, 'sub_item_id' => 122, 'qty' => 8, 'sn_filled' => 4, 'received_at' => '8 Jul 2026', 'by' => 'Budi Santoso', 'base' => 'sn_assigned', 'overrides' => [
                1 => ['sn' => 'ZTE10G-88341', 'status' => 'installed', 'destination' => 'POP Cikarang Utara'],
                2 => ['sn' => 'ZTE10G-88342', 'status' => 'delivered', 'destination' => 'POP Cikarang Utara'],
                3 => ['sn' => 'ZTE10G-88357', 'status' => 'in_transit', 'destination' => 'POP Jababeka'],
                4 => ['sn' => 'ZTE10G-88360'],
                8 => ['status' => 'damaged', 'destination' => 'Gudang Pusat'],
            ]],
            ['id' => 502, 'code' => 'GR-2026-0009', 'spektek_id' => 12, 'sub_item_id' => 123, 'qty' => 16, 'sn_filled' => 0, 'received_at' => '10 Jul 2026', 'by' => 'Budi Santoso', 'base' => 'sn_assigned', 'overrides' => []],
            ['id' => 520, 'code' => 'GR-2026-0004', 'spektek_id' => 21, 'sub_item_id' => 211, 'qty' => 30, 'sn_filled' => 30, 'received_at' => '1 Jul 2026', 'by' => 'Citra Lestari', 'base' => 'sn_assigned', 'overrides' => $dist520],
            ['id' => 521, 'code' => 'GR-2026-0006', 'spektek_id' => 22, 'sub_item_id' => 221, 'qty' => 15, 'sn_filled' => 15, 'received_at' => '20 Jun 2026', 'by' => 'Citra Lestari', 'base' => 'delivered', 'overrides' => []],
            ['id' => 540, 'code' => 'GR-2026-0008', 'spektek_id' => 41, 'sub_item_id' => 411, 'qty' => 10, 'sn_filled' => 10, 'received_at' => '10 Mei 2026', 'by' => 'Dedi Kurniawan', 'base' => 'installed', 'overrides' => [
                9 => ['status' => 'damaged', 'destination' => 'Gudang Pusat'],
                10 => ['status' => 'damaged', 'destination' => 'Gudang Pusat'],
            ]],
        ];

        $receipts = [];
        $units = [];

        foreach ($receiptSeed as $r) {
            $spektek = $spekteks[$r['spektek_id']];
            $subItem = collect($spektek['sub_items'])->firstWhere('id', $r['sub_item_id']);

            $receipts[$r['id']] = [
                'id' => $r['id'], 'code' => $r['code'], 'project_id' => $spektek['project_id'],
                'spektek_id' => $r['spektek_id'], 'spektek_name' => $spektek['name'],
                'sub_item_id' => $r['sub_item_id'], 'sub_item' => $subItem['name'],
                'qty' => $r['qty'], 'received_at' => $r['received_at'], 'by' => $r['by'],
            ];

            $prefix = str_replace('GR-2026-', 'INV-', $r['code']);

            for ($no = 1; $no <= $r['qty']; $no++) {
                $id = $r['id'] * 100 + $no;
                $hasSn = $no <= $r['sn_filled'];

                $unit = [
                    'id' => $id, 'no' => $no, 'code' => sprintf('%s-%03d', $prefix, $no),
                    'receipt_id' => $r['id'], 'receipt_code' => $r['code'], 'receipt_by' => $r['by'],
                    'received_at' => $r['received_at'],
                    'spektek_id' => $r['spektek_id'], 'spektek_name' => $spektek['name'],
                    'sub_item_id' => $r['sub_item_id'], 'item' => $subItem['name'],
                    'project_id' => $spektek['project_id'],
                    'sn' => $hasSn ? sprintf('SN-%d-%03d', $r['id'], $no) : null,
                    'status' => $hasSn ? $r['base'] : 'empty',
                    'destination' => null,
                ];

                if (isset($r['overrides'][$no])) {
                    $unit = [...$unit, ...$r['overrides'][$no]];
                }

                $units[$id] = $unit;
            }
        }

        return [
            'projects' => $projects,
            'spekteks' => $spekteks,
            'receipts' => $receipts,
            'units' => $units,
            'seq' => ['receipt' => 9],
        ];
    }
}
