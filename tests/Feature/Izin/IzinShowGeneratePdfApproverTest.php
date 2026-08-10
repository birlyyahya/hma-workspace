<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\IzinCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function seedIzinApproverRoles(): array
{
    $it = Department::create(['code' => 'it', 'name' => 'IT', 'parent_id' => null, 'is_active' => true]);
    $infra = Department::create(['code' => 'it-infra', 'name' => 'IT Infrastructure', 'parent_id' => $it->id, 'is_active' => true]);
    $software = Department::create(['code' => 'it-software', 'name' => 'IT Software', 'parent_id' => $it->id, 'is_active' => true]);

    $manager = Role::factory()->manager()->create();
    $asmen = Role::factory()->asmen()->create(['department_id' => $it->id]);
    $spvInfra = Role::factory()->spvInfra()->create(['department_id' => $infra->id]);
    $staffInfra = Role::factory()->staff('it-infra')->create(['department_id' => $infra->id]);
    $staffSoftware = Role::factory()->staff('it-software')->create(['department_id' => $software->id]);

    User::factory()->create(['role_id' => $manager->id, 'name' => 'Pak Manager']);
    User::factory()->create(['role_id' => $asmen->id, 'name' => 'Bu Asmen']);
    User::factory()->create(['role_id' => $spvInfra->id, 'name' => 'Pak SPV Infra']);

    return compact('staffInfra', 'staffSoftware');
}

function fakeIzinDetailFor(string $username): array
{
    return [
        'success' => true,
        'data' => [
            'id' => 1,
            'status' => '1',
            'reason' => 'Sakit',
            'description' => 'Keperluan pribadi',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'users' => [
                'name' => 'Pemohon',
                'username' => $username,
                'divisi' => 'IT',
            ],
        ],
    ];
}

function fakePdf(): array
{
    $captured = null;

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdf.izin-pdf', Mockery::on(function ($data) use (&$captured) {
            $captured = $data;

            return true;
        }))
        ->andReturnSelf();

    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-fake');

    return [function () use (&$captured) {
        return $captured;
    }];
}

test('uses SPV IT Infra as the second approver when the requester is in the IT Infra department', function () {
    $roles = seedIzinApproverRoles();
    $pemohon = User::factory()->create(['role_id' => $roles['staffInfra']->id, 'username' => 'staffinfra']);

    mock(IzinCache::class, function ($mock) use ($pemohon) {
        $mock->shouldReceive('detail')->andReturn(fakeIzinDetailFor($pemohon->username));
    });

    [$captured] = fakePdf();

    Volt::actingAs($pemohon)->test('izin.izin-show', ['id' => 1])
        ->call('generatePDF', 1);

    expect($captured()['izin']['asmen_name'])->toBe('Pak SPV Infra');
});

test('falls back to asmen as the second approver when the requester is not in the IT Infra department', function () {
    $roles = seedIzinApproverRoles();
    $pemohon = User::factory()->create(['role_id' => $roles['staffSoftware']->id, 'username' => 'staffsoftware']);

    mock(IzinCache::class, function ($mock) use ($pemohon) {
        $mock->shouldReceive('detail')->andReturn(fakeIzinDetailFor($pemohon->username));
    });

    [$captured] = fakePdf();

    Volt::actingAs($pemohon)->test('izin.izin-show', ['id' => 2])
        ->call('generatePDF', 2);

    expect($captured()['izin']['asmen_name'])->toBe('Bu Asmen');
});
