<?php

use App\Models\User;
use App\Services\CaWriter;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(function () {
    config(['services.api_ca' => 'http://api.test']);
});

function topupLaporan(array $balance)
{
    return Volt::test('cashadvance.modals.topup-laporan', ['balance' => $balance]);
}

test('shows the reimbursement label and warning when the closing balance is negative', function () {
    $this->actingAs(User::factory()->create());

    topupLaporan(['kode_ca' => 'CA-PL-02', 'total_pengeluaran' => 2_280_000, 'saldo_akhir' => -280_000])
        ->assertSee('Bukti TF Penyelesaian Reimbursement dari Management')
        ->assertSee('Saldo akhir periode ini minus')
        ->assertDontSee('Bukti TF Penyelesaian Dokumen Resmi');
});

test('shows the official document settlement label when the closing balance is not negative', function () {
    $this->actingAs(User::factory()->create());

    topupLaporan(['kode_ca' => 'CA-PL-01', 'total_pengeluaran' => 300_000, 'saldo_akhir' => 1_700_000])
        ->assertSee('Bukti TF Penyelesaian Dokumen Resmi')
        ->assertDontSee('Bukti TF Penyelesaian Reimbursement dari Management');
});

test('topup requires the topup amount and bukti_setor before it can be executed', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)->shouldNotReceive('topup');

    topupLaporan(['kode_ca' => 'CA-PL-01', 'total_pengeluaran' => 300_000, 'saldo_akhir' => 1_700_000])
        ->call('topup')
        ->assertHasErrors(['totalPenerimaan' => 'required', 'buktiSetor' => 'required']);
});

test('topup delegates to the writer with total_penerimaan and bukti_setor, and notifies the dashboard', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)
        ->shouldReceive('topup')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool => ($payload['total_penerimaan'] ?? null) === 8_000_000
            && isset($payload['bukti_setor']['contents'], $payload['bukti_setor']['name'])
            && $payload['bukti_setor']['name'] === 'setoran.pdf'))
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    $bukti = UploadedFile::fake()->create('setoran.pdf', 100, 'application/pdf');

    topupLaporan(['kode_ca' => 'CA-PL-01', 'total_pengeluaran' => 300_000, 'saldo_akhir' => 1_700_000])
        ->set('totalPenerimaan', '8000000')
        ->set('buktiSetor', $bukti)
        ->call('topup')
        ->assertHasNoErrors()
        ->assertSet('buktiSetor', null)
        ->assertDispatched('ca-updated')
        ->assertDispatched('riwayat-updated');
});

test('topup shows an error toast and keeps the form when the writer fails', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)
        ->shouldReceive('topup')
        ->once()
        ->andReturn(['ok' => false, 'body' => ['message' => 'API menolak permintaan'], 'status' => 422, 'error' => null]);

    $bukti = UploadedFile::fake()->create('setoran.pdf', 100, 'application/pdf');

    topupLaporan(['kode_ca' => 'CA-PL-01', 'total_pengeluaran' => 300_000, 'saldo_akhir' => 1_700_000])
        ->set('totalPenerimaan', '8000000')
        ->set('buktiSetor', $bukti)
        ->call('topup')
        ->assertNotDispatched('ca-updated');
});
