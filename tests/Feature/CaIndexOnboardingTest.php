<?php

use App\Models\User;
use App\Services\CaWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(function () {
    config(['services.api_ca' => 'http://api.test']);
});

function fakeCaDashboard(array $cardLeft): void
{
    Http::fake([
        '*/cashadvance*' => Http::response([
            'card_left' => $cardLeft,
            'card_right' => ['data' => []],
            'chart' => ['series' => []],
            'categories' => ['Transport', 'Konsumsi'],
        ], 200),
        '*/transaksi-wallet-pl/*' => Http::response(['data' => []], 200),
        '*/riwayat-wallet-kegiatan*' => Http::response(['data' => []], 200),
    ]);
}

test('shows the onboarding top up UI instead of the dashboard when the user has no CA', function () {
    fakeCaDashboard([]);

    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->assertSee('Anda belum memiliki Cash Advance')
        ->assertSee('Top up Pertama')
        ->assertDontSee('Saldo aktif')
        ->assertDontSee('Transaksi Terbaru');
});

test('shows the dashboard instead of the onboarding UI when the user has a CA', function () {
    fakeCaDashboard(['kode_ca' => 'CA-PL-01', 'sisa_saldo' => 1_000_000]);

    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->assertSee('Saldo aktif')
        ->assertSee('CA-PL-01')
        ->assertDontSee('Anda belum memiliki Cash Advance');
});

test('a transaction with a bukti renders a MinIO preview URL', function () {
    config(['services.aws_url' => 'https://storage.test/']);

    Http::fake([
        '*/cashadvance*' => Http::response([
            'card_left' => ['kode_ca' => 'CA-PL-01', 'sisa_saldo' => 1_000_000],
            'card_right' => ['data' => []],
            'chart' => ['series' => []],
            'categories' => ['Transport'],
        ], 200),
        '*/transaksi-wallet-pl/*' => Http::response(['data' => [[
            'id_transaksi' => 9,
            'dompet_id' => 1,
            'deskripsi' => 'Taksi bandara',
            'kategori' => 'Transport',
            'created_at' => '2026-07-01 10:00:00',
            'jenis' => 'pengeluaran',
            'jumlah' => 75_000,
            'saldo_setelah' => 925_000,
            'bukti' => 'bukti-transaksi-capl/2026/CA-2026-003-25/contoh.pdf',
        ]]], 200),
        '*/riwayat-wallet-kegiatan*' => Http::response(['data' => []], 200),
    ]);

    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->assertSee('https://storage.test/cashadvance/bukti-transaksi-capl/2026/CA-2026-003-25/contoh.pdf', false);
});

test('submitInitialTopup requires the topup amount and bukti_setor', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)->shouldNotReceive('topupCaPL');

    Volt::test('cashadvance.modals.topup-pertama')
        ->call('submitInitialTopup')
        ->assertHasErrors(['totalPenerimaan' => 'required', 'buktiSetor' => 'required']);
});

test('submitInitialTopup delegates to the writer and notifies the dashboard to open', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)
        ->shouldReceive('topupCaPL')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool => ($payload['total_penerimaan'] ?? null) === 2_000_000
            && isset($payload['bukti_setor']['contents'], $payload['bukti_setor']['name'])))
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    Volt::test('cashadvance.modals.topup-pertama')
        ->set('totalPenerimaan', '2000000')
        ->set('buktiSetor', UploadedFile::fake()->create('setoran.pdf', 100, 'application/pdf'))
        ->call('submitInitialTopup')
        ->assertHasNoErrors()
        ->assertDispatched('ca-updated');
});

test('submitInitialTopup does not open the dashboard when the writer fails', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)
        ->shouldReceive('topupCaPL')
        ->once()
        ->andReturn(['ok' => false, 'body' => [], 'status' => 500, 'error' => 'boom']);

    Volt::test('cashadvance.modals.topup-pertama')
        ->set('totalPenerimaan', '2000000')
        ->set('buktiSetor', UploadedFile::fake()->create('setoran.pdf', 100, 'application/pdf'))
        ->call('submitInitialTopup')
        ->assertNotDispatched('ca-updated');
});
