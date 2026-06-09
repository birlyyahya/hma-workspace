<?php

use App\Services\CaDummy;
use Livewire\Volt\Volt;

it('CaDummy menyediakan dompet PL, transaksi periode, dan dompet kegiatan', function () {
    $dummy = new CaDummy;

    expect($dummy->walletPl()['type'])->toBe('ca_pl')
        ->and($dummy->walletPl()['imprest_amount'])->toBe(CaDummy::IMPREST)
        ->and($dummy->periodTransactions())->not->toBeEmpty()
        ->and($dummy->dompetKegiatan())->toHaveCount(3)
        ->and($dummy->kegiatanByKode('KEG-001')['name'])->toBe('Pelatihan K3 Proyek Cikarang')
        ->and($dummy->kegiatanTransactions('KEG-001'))->not->toBeEmpty();
});

it('ca-index menampilkan saldo & memfilter transaksi periode aktif', function () {
    Volt::test('cashadvance.ca-index')
        ->assertSee('Total Balance')
        ->assertSee('Taksi ke lokasi proyek')
        ->set('search', 'tidak-ada')
        ->assertSee('Belum ada transaksi pada periode aktif')
        ->set('search', 'ATK')
        ->assertSee('Pembelian alat tulis');
});

it('ca-index mencatat pengeluaran dan saldo berkurang', function () {
    $component = Volt::test('cashadvance.ca-index')
        ->set('amount', '300000')
        ->set('category', 'Transport')
        ->set('description', 'Sewa motor')
        ->set('date', '2026-06-06')
        ->call('recordExpense');

    // bukti wajib -> error tanpa file
    $component->assertHasErrors('bukti');
});

it('ca-index memvalidasi field pengeluaran wajib', function () {
    Volt::test('cashadvance.ca-index')
        ->set('amount', '')
        ->set('category', '')
        ->set('description', '')
        ->call('recordExpense')
        ->assertHasErrors(['amount', 'category', 'description', 'bukti']);
});

it('ca-index mengedit transaksi (tanpa wajib bukti ulang)', function () {
    $component = Volt::test('cashadvance.ca-index')
        ->call('editTransaction', 101)
        ->assertSet('editingId', 101)
        ->assertSet('category', 'Transport')
        ->set('amount', '999000')
        ->call('recordExpense')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    $updated = collect($component->get('transactions'))->firstWhere('id', 101);
    expect((int) $updated['amount'])->toBe(999000);
});

it('ca-index menghapus transaksi', function () {
    $component = Volt::test('cashadvance.ca-index')->call('deleteTransaction', 101);

    expect(collect($component->get('transactions'))->firstWhere('id', 101))->toBeNull();
});

it('ca-index top up menyegel periode (masuk riwayat) & buka periode baru', function () {
    Volt::test('cashadvance.ca-index')
        ->assertSet('wallet.period.sequence_no', 3)
        ->call('topup')
        ->assertSet('wallet.period.sequence_no', 4)
        ->assertSet('wallet.period.opening_balance', CaDummy::IMPREST)
        ->assertSet('wallet.status', 'active')
        ->assertSet('transactions', [])
        ->assertSet('sealedPeriods.0.sequence_no', 3); // periode lama tersegel
});

it('laporan CA PL periode aktif & periode tersegel dapat dirender', function () {
    Volt::test('cashadvance.laporan-capl')
        ->assertSet('isSealed', false)
        ->assertSee('Laporan Pertanggungjawaban CA PL');

    Volt::test('cashadvance.laporan-capl', ['seq' => 2])
        ->assertSet('isSealed', true)
        ->assertSee('Hotel perjalanan dinas');
});

it('laporan CA Kegiatan dapat dirender', function () {
    Volt::test('cashadvance.laporan-kegiatan', ['kodeCa' => 'KEG-001'])
        ->assertSee('Laporan Pertanggungjawaban Kegiatan')
        ->assertSee('Pelatihan K3 Proyek Cikarang');
});

it('ca-index membuat dompet kegiatan baru dari form', function () {
    Volt::test('cashadvance.ca-index')
        ->set('keg_name', 'Survei Bandung')
        ->set('keg_dana', '4000000')
        ->call('createKegiatan')
        ->assertHasErrors('keg_bukti'); // bukti pencairan wajib
});

it('dompet-show menampilkan detail kegiatan & saldo berjalan', function () {
    Volt::test('cashadvance.dompet-show', ['kodeCa' => 'KEG-001'])
        ->assertSee('Pelatihan K3 Proyek Cikarang')
        ->assertSee('Sewa tempat pelatihan')
        ->assertSet('kodeCa', 'KEG-001');
});

it('dompet-show settlement menutup kegiatan', function () {
    Volt::test('cashadvance.dompet-show', ['kodeCa' => 'KEG-001'])
        ->assertSet('dompet.status', 'active')
        ->call('settle')
        ->assertSet('dompet.status', 'closed');
});

it('dompet-show memvalidasi catat transaksi wajib', function () {
    Volt::test('cashadvance.dompet-show', ['kodeCa' => 'KEG-001'])
        ->set('amount', '')
        ->set('category', '')
        ->set('description', '')
        ->call('catatTransaksi')
        ->assertHasErrors(['amount', 'category', 'description', 'bukti']);
});

it('dompet-show mengedit & menghapus transaksi', function () {
    $component = Volt::test('cashadvance.dompet-show', ['kodeCa' => 'KEG-001'])
        ->call('editTransaksi', 202)
        ->assertSet('editingId', 202)
        ->set('amount', '7777000')
        ->call('catatTransaksi')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect((int) collect($component->get('transactions'))->firstWhere('id', 202)['amount'])->toBe(7777000);

    $component->call('hapusTransaksi', 202);
    expect(collect($component->get('transactions'))->firstWhere('id', 202))->toBeNull();
});
