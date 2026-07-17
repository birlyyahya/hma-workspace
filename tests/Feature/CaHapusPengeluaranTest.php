<?php

use App\Models\User;
use App\Services\CaWriter;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(function () {
    config(['services.api_ca' => 'http://api.test']);
});

function hapusPengeluaran()
{
    return Volt::test('cashadvance.modals.hapus-pengeluaran', ['kodeCa' => 'CA-PL-01']);
}

test('ca-confirm-delete event stores the transaction id', function () {
    $this->actingAs(User::factory()->create());

    hapusPengeluaran()
        ->dispatch('ca-confirm-delete', id: 9)
        ->assertSet('deleteTransactionId', 9);
});

test('deleteTransaction errors when no transaction is selected', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)->shouldNotReceive('deleteTransactionCaPL');

    hapusPengeluaran()
        ->call('deleteTransaction')
        ->assertNotDispatched('ca-updated');
});

test('deleteTransaction delegates to the writer and notifies the dashboard', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)
        ->shouldReceive('deleteTransactionCaPL')
        ->once()
        ->with('CA-PL-01', 9)
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    hapusPengeluaran()
        ->set('deleteTransactionId', 9)
        ->call('deleteTransaction')
        ->assertSet('deleteTransactionId', null)
        ->assertDispatched('ca-updated');
});
