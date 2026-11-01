<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IzinCache;
use App\Services\IzinWriter;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

function izinFixture(): array
{
    return [
        'id' => 2413,
        'user_name' => 'Budi Santoso',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-01',
        'start_time' => '09:46',
        'end_time' => '17:00',
        'reason' => 'Dinas luar kota',
        'description' => 'Kunjungan klien',
        'status' => '2',
        'hrd_checked' => 0,
    ];
}

function hrdUser(): User
{
    $permission = Permission::firstOrCreate(['name' => 'izin.view.all'], ['module' => 'izin', 'action' => 'view.all', 'label' => 'Lihat semua izin']);
    $role = Role::factory()->create();
    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id, 'username' => 'hrd.user']);
}

beforeEach(function () {
    Livewire::withoutLazyLoading();
});

test('updateIzinStatus mengirim payload lama dan hanya status yang berbeda', function () {
    Http::fake(['*update-izin-saya' => Http::response(['success' => true])]);
    mock(IzinCache::class)->shouldReceive('flush')->once();

    $result = app(IzinWriter::class)->updateIzinStatus(izinFixture(), 'hrd_checked', 'hrd.user');

    expect($result['ok'])->toBeTrue();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/global/izin/update-izin-saya')
        && $request['id'] === '2413'
        && $request['start_date'] === '2026-11-01'
        && $request['end_time'] === '17:00'
        && $request['alasan'] === 'Dinas luar kota'
        && $request['deskripsi'] === 'Kunjungan klien'
        && $request['username'] === 'hrd.user'
        && $request['status'] === 'hrd_checked');
});

test('updateIzinStatus mengembalikan ok=false saat API gagal', function () {
    Http::fake(['*update-izin-saya' => Http::response(['success' => false, 'message' => 'Gagal'], 422)]);

    $result = app(IzinWriter::class)->updateIzinStatus(izinFixture(), 'cancel', 'hrd.user');

    expect($result['ok'])->toBeFalse()
        ->and($result['body']['message'])->toBe('Gagal');
});

test('HRD bisa menyetujui izin dan list di-refresh', function () {
    $this->actingAs(hrdUser());

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('list')->andReturn(['data' => [izinFixture()], 'total' => 1, 'last_page' => 1]);
        $mock->shouldReceive('detail')->with(2413)->andReturn(['success' => true, 'data' => izinFixture()]);
    });
    mock(IzinWriter::class)
        ->shouldReceive('updateIzinStatus')
        ->once()
        ->withArgs(fn ($izin, $status, $username) => $status === 'hrd_checked' && $username === 'hrd.user')
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    Volt::test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->assertSet('hrdConfirmId', 2413)
        ->set('hrdAction', 'approve')
        ->call('confirmHrd')
        ->assertSet('hrdConfirmId', null)
        ->assertSet('hrdAction', '');
});

test('HRD bisa menolak izin dengan status cancel', function () {
    $this->actingAs(hrdUser());

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('list')->andReturn(['data' => [izinFixture()], 'total' => 1, 'last_page' => 1]);
        $mock->shouldReceive('detail')->with(2413)->andReturn(['success' => true, 'data' => izinFixture()]);
    });
    mock(IzinWriter::class)
        ->shouldReceive('updateIzinStatus')
        ->once()
        ->withArgs(fn ($izin, $status) => $status === 'cancel')
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    Volt::test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->set('hrdAction', 'reject')
        ->call('confirmHrd')
        ->assertSet('hrdConfirmId', null);
});

test('confirmHrd tidak memanggil API tanpa memilih keputusan', function () {
    $this->actingAs(hrdUser());

    mock(IzinCache::class)->shouldReceive('list')->andReturn(['data' => [izinFixture()], 'total' => 1, 'last_page' => 1]);
    mock(IzinWriter::class)->shouldNotReceive('updateIzinStatus');

    Volt::test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->call('confirmHrd')
        ->assertSet('hrdConfirmId', 2413);
});

test('user tanpa izin.view.all tidak bisa memproses izin', function () {
    $this->actingAs(User::factory()->create(['role_id' => Role::factory()->create()->id]));

    mock(IzinCache::class)->shouldReceive('list')->andReturn(['data' => [izinFixture()], 'total' => 1, 'last_page' => 1]);
    mock(IzinWriter::class)->shouldNotReceive('updateIzinStatus');

    Volt::test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->set('hrdAction', 'approve')
        ->call('confirmHrd')
        ->assertSet('hrdConfirmId', 2413);
});

test('gagal dari API menjaga modal tetap terbuka', function () {
    $this->actingAs(hrdUser());

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('list')->andReturn(['data' => [izinFixture()], 'total' => 1, 'last_page' => 1]);
        $mock->shouldReceive('detail')->andReturn(['success' => true, 'data' => izinFixture()]);
    });
    mock(IzinWriter::class)
        ->shouldReceive('updateIzinStatus')
        ->once()
        ->andReturn(['ok' => false, 'body' => ['message' => 'Gagal'], 'status' => 422, 'error' => null]);

    Volt::test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->set('hrdAction', 'approve')
        ->call('confirmHrd')
        ->assertSet('hrdConfirmId', 2413);
});

test('status izin tampil sesuai tahap: menunggu, disetujui IT, disetujui HRD, ditolak', function () {
    $this->actingAs(hrdUser());

    $rows = [
        [...izinFixture(), 'id' => 1, 'status' => '1', 'hrd_checked' => 0],
        [...izinFixture(), 'id' => 2, 'status' => '2', 'hrd_checked' => 0],
        [...izinFixture(), 'id' => 3, 'status' => '2', 'hrd_checked' => 1],
        [...izinFixture(), 'id' => 4, 'status' => '3', 'hrd_checked' => 0],
    ];

    mock(IzinCache::class)->shouldReceive('list')->andReturn(['data' => $rows, 'total' => 4, 'last_page' => 1]);

    Volt::test('izin.izin-table', ['laporan' => true])
        ->assertSee('Menunggu IT')
        ->assertSee('Disetujui IT')
        ->assertSee('Disetujui HRD')
        ->assertSee('Ditolak')
        ->assertSee('Izin dibatalkan');
});
