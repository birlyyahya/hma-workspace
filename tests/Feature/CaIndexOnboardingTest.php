<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

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
        ->assertDontSee('Total Balance')
        ->assertDontSee('Recent Transaction');
});

test('shows the dashboard instead of the onboarding UI when the user has a CA', function () {
    fakeCaDashboard(['kode_ca' => 'CA-PL-01', 'sisa_saldo' => 1_000_000]);

    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->assertSee('Total Balance')
        ->assertSee('CA-PL-01')
        ->assertDontSee('Anda belum memiliki Cash Advance');
});

test('submitInitialTopup requires a nominal and a bukti transfer', function () {
    fakeCaDashboard([]);

    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->call('submitInitialTopup')
        ->assertHasErrors(['topupJumlah' => 'required', 'topupBukti' => 'required']);
});

test('submitInitialTopup passes validation with a nominal and a valid bukti', function () {
    fakeCaDashboard([]);

    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->set('topupJumlah', 2_000_000)
        ->set('topupBukti', UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'))
        ->call('submitInitialTopup')
        ->assertHasNoErrors();
});
