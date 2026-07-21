<?php

use App\Models\User;
use App\Services\InventarisCache;
use App\Services\InventarisDummy;
use App\Services\InventarisWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

// =============================================================================
// Service API (InventarisCache / InventarisWriter) — dipertahankan untuk fase
// integrasi API nanti; belum dipakai UI yang sekarang berbasis InventarisDummy.
// =============================================================================

function makeInventarisWriter(): InventarisWriter
{
    return new InventarisWriter('http://api.test', new InventarisCache('http://api.test'));
}

test('cache list returns the full payload (data + pagination)', function () {
    Http::fake(['*/inventaris/list*' => Http::response(['data' => [['id' => 1, 'nama' => 'Laptop']], 'pagination' => ['last_page' => 1]], 200)]);

    $result = (new InventarisCache('http://api.test'))->list(['search' => 'laptop']);

    expect($result['data'])->toBe([['id' => 1, 'nama' => 'Laptop']])
        ->and($result['pagination'])->toBe(['last_page' => 1]);
});

test('cache list returns empty default when connection fails, without throwing', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    expect((new InventarisCache('http://api.test'))->list())->toBe(['data' => []]);
});

test('writer createItem succeeds only when body success is true', function () {
    Http::fake(['*/inventaris/create' => Http::response(['success' => true, 'data' => ['id' => 9]], 200)]);

    expect(makeInventarisWriter()->createItem(['nama' => 'Printer'])['ok'])->toBeTrue();
});

test('writer deleteItem never throws on transport failure', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    $result = makeInventarisWriter()->deleteItem(3);

    expect($result['ok'])->toBeFalse()->and($result['error'])->not->toBeNull();
});

// =============================================================================
// InventarisDummy — data & aksi demo (session-backed)
// =============================================================================

test('seed menghitung ringkasan project dari unit', function () {
    $dummy = new InventarisDummy;
    $byId = collect($dummy->projects())->keyBy('id');

    expect($byId[101]['total_units'])->toBe(120)
        ->and($byId[101]['sn_units'])->toBe(56)
        ->and($byId[101]['status_color'])->toBe('amber')
        ->and($byId[102]['status_label'])->toBe('Lengkap')
        ->and($byId[103]['status_label'])->toBe('Belum mulai')
        ->and($byId[104]['status_label'])->toBe('2 unit rusak');
});

test('findBySnOrCode menemukan unit lewat SN maupun kode', function () {
    $dummy = new InventarisDummy;

    expect($dummy->findBySnOrCode('ZTE10G-88342')['id'])->toBe(50102)
        ->and($dummy->findBySnOrCode('INV-0007-005')['status'])->toBe('empty')
        ->and($dummy->findBySnOrCode('tidak-ada'))->toBeNull();
});

test('generate membuat batch baru dan menambah jumlah unit tergenerate', function () {
    $dummy = new InventarisDummy;

    $before = collect($dummy->spektek(12)['sub_items'])->firstWhere('id', 123)['generated'];
    $receiptId = $dummy->generate(12, 123, 16);
    $after = collect($dummy->spektek(12)['sub_items'])->firstWhere('id', 123)['generated'];

    expect($receiptId)->toBeGreaterThan(0)
        ->and($after)->toBe($before + 16)
        ->and($dummy->unitsForReceipt($receiptId))->toHaveCount(16)
        ->and(collect($dummy->unitsForReceipt($receiptId))->every(fn ($u) => $u['status'] === 'empty'))->toBeTrue();
});

test('generate di-clamp ke sisa qty spektek', function () {
    $dummy = new InventarisDummy;

    // Patchcord sisa 16; minta 999 → hanya 16 dibuat.
    $receiptId = $dummy->generate(12, 123, 999);

    expect($dummy->unitsForReceipt($receiptId))->toHaveCount(16);
});

test('assignSn mengisi SN dan menolak duplikat', function () {
    $dummy = new InventarisDummy;

    expect($dummy->assignSn(50105, 'NEW-SN-001')['ok'])->toBeTrue()
        ->and($dummy->unit(50105)['sn'])->toBe('NEW-SN-001')
        ->and($dummy->unit(50105)['status'])->toBe('sn_assigned')
        ->and($dummy->assignSn(50106, 'NEW-SN-001')['ok'])->toBeFalse();
});

test('assignNextEmpty mengisi unit kosong pertama dalam batch', function () {
    $dummy = new InventarisDummy;

    $result = $dummy->assignNextEmpty(501, 'SCAN-001');

    expect($result['ok'])->toBeTrue()
        ->and($result['unit'])->toBe('INV-0007-005')
        ->and($dummy->unit(50105)['sn'])->toBe('SCAN-001');
});

test('importSn assign berurutan, lewati duplikat & kelebihan', function () {
    $dummy = new InventarisDummy;

    // Batch 501 punya 3 unit kosong (5,6,7). Kirim 4 SN dengan 1 duplikat.
    $summary = $dummy->importSn(501, ['IMP-1', 'IMP-2', 'IMP-1', 'IMP-3', 'IMP-4']);

    expect($summary['assigned'])->toBe(3)
        ->and($summary['duplicates'])->toBe(1)
        ->and($summary['overflow'])->toBe(1);
});

test('setStatus menolak advance unit tanpa SN, tapi izinkan tandai rusak', function () {
    $dummy = new InventarisDummy;

    expect($dummy->setStatus(50105, 'in_transit')['ok'])->toBeFalse()
        ->and($dummy->setStatus(50105, 'damaged')['ok'])->toBeTrue()
        ->and($dummy->unit(50105)['status'])->toBe('damaged');
});

test('reset mengembalikan state ke kondisi awal', function () {
    $dummy = new InventarisDummy;

    $dummy->assignSn(50105, 'TEMP-SN');
    expect($dummy->unit(50105)['sn'])->toBe('TEMP-SN');

    $dummy->reset();
    expect($dummy->unit(50105)['sn'])->toBeNull();
});

test('nextStatus mengikuti urutan lifecycle', function () {
    expect(InventarisDummy::nextStatus('sn_assigned'))->toBe('in_transit')
        ->and(InventarisDummy::nextStatus('delivered'))->toBe('installed')
        ->and(InventarisDummy::nextStatus('installed'))->toBeNull()
        ->and(InventarisDummy::nextStatus('empty'))->toBeNull();
});

test('batch 520 tersegmentasi ke dua satker plus grup belum dialokasikan', function () {
    $groups = collect((new InventarisDummy)->unitsGroupedByDestination(520));

    expect($groups)->toHaveCount(3)
        ->and($groups->firstWhere('destination', 'Satker A · Dinas Pendidikan')['count'])->toBe(10)
        ->and($groups->firstWhere('destination', 'Satker B · Dinas Kesehatan')['count'])->toBe(10)
        // Grup null (belum dialokasikan) selalu terakhir.
        ->and($groups->last()['destination'])->toBeNull()
        ->and($groups->last()['count'])->toBe(10);
});

test('allocateToSatker mengambil unit belum bersatker sejumlah yang diminta', function () {
    $dummy = new InventarisDummy;

    // Batch 520 punya 10 unit belum dialokasikan; minta 6.
    $n = $dummy->allocateToSatker(520, 'Satker C · Dinas PU', 6);

    expect($n)->toBe(6)
        ->and(collect($dummy->unitsForReceipt(520))->where('destination', 'Satker C · Dinas PU')->count())->toBe(6);
});

test('allocateToSatker di-clamp ke jumlah unit tersedia', function () {
    $dummy = new InventarisDummy;

    expect($dummy->allocateToSatker(520, 'Satker D · Dinas Sosial', 999))->toBe(10);
});

test('assignUnitsToSatker menetapkan satker untuk unit terpilih', function () {
    $dummy = new InventarisDummy;

    $n = $dummy->assignUnitsToSatker([50105, 50106], 'Satker A · Dinas Pendidikan');

    expect($n)->toBe(2)
        ->and($dummy->unit(50105)['destination'])->toBe('Satker A · Dinas Pendidikan');
});

test('bulkSetStatus melewati unit tanpa SN kecuali status rusak', function () {
    $dummy = new InventarisDummy;

    // 50101 ber-SN, 50105 & 50106 kosong.
    $result = $dummy->bulkSetStatus([50101, 50105, 50106], 'in_transit');

    expect($result['applied'])->toBe(1)
        ->and($result['skipped'])->toBe(2)
        ->and($dummy->bulkSetStatus([50105], 'damaged')['applied'])->toBe(1);
});

test('advanceGroup memajukan seluruh unit satu satker satu tahap', function () {
    $dummy = new InventarisDummy;

    // Satker B di batch 520 berstatus delivered → jadi installed.
    $result = $dummy->advanceGroup(520, 'Satker B · Dinas Kesehatan');

    expect($result['moved'])->toBe(10)
        ->and(collect($dummy->unitsForReceipt(520))->where('destination', 'Satker B · Dinas Kesehatan')->every(fn ($u) => $u['status'] === 'installed'))->toBeTrue();
});

// =============================================================================
// Halaman (render + aksi Livewire)
// =============================================================================

test('halaman pilih project menampilkan kartu project', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris'))
        ->assertOk()
        ->assertSee('Pembangunan Jaringan FO Cikarang')
        ->assertSee('Renovasi Jaringan Listrik Gudang C');
});

test('search SN di index mengarahkan ke detail unit', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.index')
        ->set('search', 'ZTE10G-88342')
        ->call('searchSn')
        ->assertRedirect(route('inventaris.unit-detail', ['unitId' => 50102]));
});

test('search SN tak ditemukan tidak redirect', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.index')
        ->set('search', 'ngawur')
        ->call('searchSn')
        ->assertNoRedirect();
});

test('filter project di index menyaring kartu', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.index')
        ->set('filter', 'cctv')
        ->assertSee('Instalasi CCTV Kawasan MM2100')
        ->assertDontSee('Pembangunan Jaringan FO Cikarang');
});

test('halaman daftar spektek berbeda per project', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.project', 102))
        ->assertOk()
        ->assertSee('Kamera Dome IP 4MP')
        ->assertDontSee('Perangkat OLT + Aksesoris');
});

test('generate batch di spektek-detail membuat unit dan redirect ke halaman unit', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.spektek-detail', ['projectId' => 101, 'spektekId' => 12])
        ->call('openGenerate', 123)
        ->assertSet('generateRemaining', 16)
        ->set('generateQty', 5)
        ->call('generate')
        ->assertRedirect();

    // Sub-item Patchcord kini bertambah 5 unit tergenerate.
    expect(collect((new InventarisDummy)->spektek(12)['sub_items'])->firstWhere('id', 123)['generated'])->toBe(21);
});

test('mode scan di halaman unit mengisi SN unit kosong berikutnya', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.units', ['goodsReceiptId' => 501])
        ->set('scanSn', 'SCAN-XYZ')
        ->call('scan')
        ->assertSee('SCAN-XYZ');

    expect((new InventarisDummy)->unit(50105)['sn'])->toBe('SCAN-XYZ');
});

test('import SN di halaman unit memunculkan pratinjau dan menerapkan assign', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.units', ['goodsReceiptId' => 501])
        ->set('importText', "IMP-A\nIMP-B")
        ->assertSee('INV-0007-005')
        ->call('confirmImport');

    expect((new InventarisDummy)->unit(50105)['sn'])->toBe('IMP-A')
        ->and((new InventarisDummy)->unit(50106)['sn'])->toBe('IMP-B');
});

test('edit SN di halaman unit memperbarui nilai', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.units', ['goodsReceiptId' => 501])
        ->call('openEdit', 50101)
        ->set('editSn', 'EDITED-SN')
        ->call('saveEdit');

    expect((new InventarisDummy)->unit(50101)['sn'])->toBe('EDITED-SN');
});

test('halaman unit: alokasi ke satker via jumlah', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.units', ['goodsReceiptId' => 520])
        ->set('allocSatker', 'Satker C · Dinas PU')
        ->set('allocCount', 4)
        ->call('allocate');

    expect(collect((new InventarisDummy)->unitsForReceipt(520))->where('destination', 'Satker C · Dinas PU')->count())->toBe(4);
});

test('halaman unit: advanceGroup memajukan status satu satker', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.units', ['goodsReceiptId' => 520])
        ->call('advanceGroup', 'Satker A · Dinas Pendidikan');

    // Satker A semula installed (mentok) → tetap installed, tidak error.
    expect(collect((new InventarisDummy)->unitsForReceipt(520))->where('destination', 'Satker B · Dinas Kesehatan')->first()['status'])->toBe('delivered');
});

test('halaman unit: bulk ubah status untuk unit terpilih', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.units', ['goodsReceiptId' => 520])
        ->set('bulkStatus', 'installed')
        ->call('openBulkStatus', [52011, 52012])
        ->call('applyBulkStatus');

    expect((new InventarisDummy)->unit(52011)['status'])->toBe('installed');
});

test('halaman unit: bulk assign satker untuk unit terpilih', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.units', ['goodsReceiptId' => 501])
        ->set('bulkSatker', 'Satker D · Dinas Sosial')
        ->call('openBulkSatker', [50105, 50106])
        ->call('applyBulkSatker');

    expect((new InventarisDummy)->unit(50105)['destination'])->toBe('Satker D · Dinas Sosial');
});

test('halaman unit menampilkan grup satker di tampilan per-satker', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.units', 520))
        ->assertOk()
        ->assertSee('Satker A · Dinas Pendidikan')
        ->assertSee('Belum dialokasikan');
});

test('halaman detail unit menampilkan SN dan timeline', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.unit-detail', 50102))
        ->assertOk()
        ->assertSee('ZTE10G-88342')
        ->assertSee('Riwayat Pergerakan');
});

test('aksi utama di detail unit meng-advance status', function () {
    $this->actingAs(User::factory()->create());

    // Unit 50103 status in_transit → advance ke delivered.
    Volt::test('inventaris.unit-detail', ['unitId' => 50103])
        ->call('primaryAction');

    expect((new InventarisDummy)->unit(50103)['status'])->toBe('delivered');
});

test('assign SN via detail unit untuk unit kosong', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.unit-detail', ['unitId' => 50105])
        ->set('actionSn', 'DETAIL-SN')
        ->call('primaryAction');

    expect((new InventarisDummy)->unit(50105)['sn'])->toBe('DETAIL-SN')
        ->and((new InventarisDummy)->unit(50105)['status'])->toBe('sn_assigned');
});

test('tandai rusak di detail unit', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.unit-detail', ['unitId' => 50102])
        ->call('markDamaged');

    expect((new InventarisDummy)->unit(50102)['status'])->toBe('damaged');
});

test('detail unit menampilkan state tidak ditemukan untuk id tak dikenal', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.unit-detail', 999999))
        ->assertOk()
        ->assertSee('tidak ditemukan');
});

test('scan: unit lengkap menampilkan detail read-only', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.scan')
        ->set('query', 'ZTE10G-88342')
        ->call('lookup')
        ->assertSee('Buka Detail')
        ->assertSee('Diterima di Site');
});

test('scan: unit kosong sebagai anggota tim memunculkan form dan menyimpan SN', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.scan')
        ->set('query', 'INV-0007-005')
        ->call('lookup')
        ->assertSee('Simpan &amp; Lengkapi Unit', false)
        ->set('assignSn', 'SCAN-COMPLETE')
        ->call('complete');

    expect((new InventarisDummy)->unit(50105)['sn'])->toBe('SCAN-COMPLETE');
});

test('scan: unit kosong sebagai non-tim terkunci tanpa form', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.scan')
        ->set('simulateOutsider', true)
        ->set('query', 'INV-0007-005')
        ->call('lookup')
        ->assertSee('Hubungi tim project')
        ->assertDontSee('Simpan &amp; Lengkapi Unit', false);
});

test('scan: kode tidak dikenal menampilkan pesan tidak ditemukan', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('inventaris.scan')
        ->set('query', 'NGACO-123')
        ->call('lookup')
        ->assertSee('tidak ditemukan');
});

test('route inventaris statis tidak tertelan wildcard projectId', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/inventaris/scan')->assertOk()->assertSee('Scan Unit');
    $this->get('/inventaris/abc')->assertNotFound();
});

test('seluruh project dummy dapat dirender', function (int $projectId) {
    $this->actingAs(User::factory()->create());
    $this->get(route('inventaris.project', $projectId))->assertOk();
})->with([101, 102, 103, 104]);

test('seluruh spektek dummy dapat dirender', function (int $projectId, int $spektekId) {
    $this->actingAs(User::factory()->create());
    $this->get(route('inventaris.spektek', [$projectId, $spektekId]))->assertOk();
})->with([
    [101, 11], [101, 12], [101, 13],
    [102, 21], [102, 22],
    [103, 31], [103, 32],
    [104, 41],
]);

test('seluruh batch dummy dapat dirender', function (int $receiptId) {
    $this->actingAs(User::factory()->create());
    $this->get(route('inventaris.units', $receiptId))->assertOk();
})->with([480, 498, 500, 501, 502, 520, 521, 540]);
