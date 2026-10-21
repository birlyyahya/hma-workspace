<?php

use App\Services\IzinCache;
use Livewire\Livewire;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(function () {
    Livewire::withoutLazyLoading();

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('list')->andReturn([
            'data' => [],
            'total' => 0,
            'last_page' => 1,
        ]);
    });
});

test('filter fields have visible labels', function () {
    Volt::test('izin.izin-table', ['laporan' => true])
        ->assertSee('Dari tanggal')
        ->assertSee('Sampai tanggal')
        ->assertSee('Urutkan')
        ->assertSee('Status');
});

test('resetFilters restores default filters and page', function () {
    Volt::test('izin.izin-table', ['laporan' => true])
        ->set('sort', 'asc')
        ->set('status', '2')
        ->set('start_date', '2026-01-01')
        ->set('end_date', '2026-01-31')
        ->call('goToPage', 3)
        ->call('resetFilters')
        ->assertSet('sort', 'desc')
        ->assertSet('status', '')
        ->assertSet('start_date', '')
        ->assertSet('end_date', '')
        ->assertSet('page', 1);
});
