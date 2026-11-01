<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\IzinHrdConfirmed;
use App\Notifications\IzinHrdRejected;
use App\Notifications\IzinSubmitted;
use App\Services\IzinCache;
use App\Services\IzinWriter;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use NotificationChannels\WebPush\WebPushChannel;

use function Pest\Laravel\mock;

function viewAllUser(string $username): User
{
    $permission = Permission::firstOrCreate(
        ['name' => 'izin.view.all'],
        ['module' => 'izin', 'action' => 'view.all', 'label' => 'Lihat semua izin'],
    );
    $role = Role::factory()->create();
    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id, 'username' => $username]);
}

function plainUser(string $username): User
{
    return User::factory()->create(['role_id' => Role::factory()->create()->id, 'username' => $username]);
}

beforeEach(function () {
    Livewire::withoutLazyLoading();
});

test('mengajukan izin memberi notifikasi ke user view all, bukan ke pemohon atau user biasa', function () {
    Notification::fake();

    $hrd = viewAllUser('hrd.user');
    $applicant = plainUser('budi');
    $other = plainUser('andi');

    mock(IzinWriter::class)
        ->shouldReceive('createIzin')
        ->once()
        ->andReturn(['ok' => true, 'body' => ['success' => true, 'data' => ['id' => 77]], 'status' => 200, 'error' => null]);

    Volt::actingAs($applicant)->test('izin.add-izin-modal')
        ->set('alasan', 'Dinas luar kota')
        ->set('deskripsi', 'Kunjungan klien')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertSentTo($hrd, IzinSubmitted::class, fn (IzinSubmitted $n, array $channels) => $n->izinId === 77
        && $n->applicantName === $applicant->name
        && in_array(WebPushChannel::class, $channels, true)
        && in_array('database', $channels, true));
    Notification::assertNotSentTo($applicant, IzinSubmitted::class);
    Notification::assertNotSentTo($other, IzinSubmitted::class);
});

test('pengajuan izin yang gagal tidak mengirim notifikasi', function () {
    Notification::fake();

    $hrd = viewAllUser('hrd.user');

    mock(IzinWriter::class)
        ->shouldReceive('createIzin')
        ->once()
        ->andReturn(['ok' => false, 'body' => [], 'status' => 500, 'error' => null]);

    Volt::actingAs(plainUser('budi'))->test('izin.add-izin-modal')
        ->set('alasan', 'Sakit')
        ->set('deskripsi', 'Demam tinggi')
        ->call('save');

    Notification::assertNothingSent();
});

test('HRD menyetujui izin memberi notifikasi ke pemohon saja', function () {
    Notification::fake();

    $hrd = viewAllUser('hrd.user');
    $applicant = plainUser('budi');
    $other = plainUser('andi');

    $izin = [
        'id' => 2413,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-01',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'reason' => 'Dinas luar kota',
        'description' => 'Kunjungan klien',
        'status' => '2',
        'hrd_checked' => 0,
        'user_name' => $applicant->name,
        'users' => ['username' => 'budi'],
    ];

    mock(IzinCache::class, function ($mock) use ($izin) {
        $mock->shouldReceive('list')->andReturn(['data' => [$izin], 'total' => 1, 'last_page' => 1]);
        $mock->shouldReceive('detail')->with(2413)->andReturn(['success' => true, 'data' => $izin]);
    });
    mock(IzinWriter::class)
        ->shouldReceive('updateIzinStatus')
        ->once()
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    Volt::actingAs($hrd)->test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->set('hrdAction', 'approve')
        ->call('confirmHrd');

    Notification::assertSentTo($applicant, IzinHrdConfirmed::class, fn (IzinHrdConfirmed $n, array $channels) => $n->izinId === 2413
        && in_array(WebPushChannel::class, $channels, true));
    Notification::assertNotSentTo($other, IzinHrdConfirmed::class);
    Notification::assertNotSentTo($hrd, IzinHrdConfirmed::class);
});

test('HRD menolak izin memberi notifikasi ditolak ke pemohon, bukan notifikasi konfirmasi', function () {
    Notification::fake();

    $hrd = viewAllUser('hrd.user');
    $applicant = plainUser('budi');

    $izin = [
        'id' => 2413, 'start_date' => '2026-11-01', 'reason' => 'Sakit', 'status' => '2',
        'hrd_checked' => 0, 'users' => ['username' => 'budi'],
    ];

    mock(IzinCache::class)->shouldReceive('list')->andReturn(['data' => [$izin], 'total' => 1, 'last_page' => 1]);
    mock(IzinWriter::class)
        ->shouldReceive('updateIzinStatus')
        ->once()
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    Volt::actingAs($hrd)->test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->set('hrdAction', 'reject')
        ->call('confirmHrd');

    Notification::assertSentTo($applicant, IzinHrdRejected::class, fn (IzinHrdRejected $n, array $channels) => $n->izinId === 2413
        && in_array(WebPushChannel::class, $channels, true));
    Notification::assertNotSentTo($applicant, IzinHrdConfirmed::class);
});

test('baris list tanpa users.username memakai detail izin untuk mencari pemohon', function () {
    Notification::fake();

    $hrd = viewAllUser('hrd.user');
    $applicant = plainUser('budi');

    $row = ['id' => 2413, 'start_date' => '2026-11-01', 'reason' => 'Sakit', 'status' => '2', 'hrd_checked' => 0, 'user_name' => 'Budi'];

    mock(IzinCache::class, function ($mock) use ($row) {
        $mock->shouldReceive('list')->andReturn(['data' => [$row], 'total' => 1, 'last_page' => 1]);
        $mock->shouldReceive('detail')->with(2413)->once()->andReturn(['success' => true, 'data' => [...$row, 'users' => ['username' => 'budi']]]);
    });
    mock(IzinWriter::class)
        ->shouldReceive('updateIzinStatus')
        ->once()
        ->andReturn(['ok' => true, 'body' => [], 'status' => 200, 'error' => null]);

    Volt::actingAs($hrd)->test('izin.izin-table', ['laporan' => true])
        ->call('openHrdConfirm', 2413)
        ->set('hrdAction', 'approve')
        ->call('confirmHrd');

    Notification::assertSentTo($applicant, IzinHrdConfirmed::class);
});

test('header notifikasi menampilkan ketiga tipe notifikasi izin', function () {
    $hrd = viewAllUser('hrd.user');
    $applicant = plainUser('budi');

    $hrd->notify(new IzinSubmitted(izinId: 5, applicantName: 'Budi Santoso', reason: 'Dinas luar kota', startDate: '2026-11-01'));
    $applicant->notify(new IzinHrdConfirmed(izinId: 5, confirmedBy: 'HRD', reason: 'Dinas luar kota', startDate: '2026-11-01'));
    $applicant->notify(new IzinHrdRejected(izinId: 6, rejectedBy: 'HRD', reason: 'Sakit', startDate: '2026-11-02'));

    Volt::actingAs($hrd)->test('components.notification-header')
        ->assertSee('Budi Santoso')
        ->assertSee('perlu konfirmasi HRD');

    Volt::actingAs($applicant)->test('components.notification-header')
        ->assertSee('telah menyetujui izin kamu')
        ->assertSee('menolak izin kamu')
        ->assertSee('Dinas luar kota');
});
