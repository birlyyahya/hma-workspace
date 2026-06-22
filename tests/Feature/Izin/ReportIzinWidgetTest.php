<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\IzinCache;
use Livewire\Livewire;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(function () {
    Livewire::withoutLazyLoading();

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('dashboard')->andReturn([
            'data' => [
                'approve_izin' => 5,
                'failed_izin' => 2,
                'all_izin' => 10,
                'group' => [],
            ],
        ]);
        $mock->shouldReceive('spdListAll')->andReturn([
            'data' => [
                ['is_submitted' => 1, 'is_approved' => 1],
                ['is_submitted' => 1, 'is_approved' => 1],
                ['is_submitted' => 1, 'is_approved' => 1],
                ['is_submitted' => 1, 'is_approved' => 0],
                ['is_submitted' => 0, 'is_approved' => 0],
            ],
        ]);
        $mock->shouldReceive('groupDashboard')->andReturn([]);
        $mock->shouldReceive('flushUser');
        $mock->shouldReceive('flushGroup');
    });
});

function itUser(): User
{
    $it = Department::firstOrCreate(['code' => 'it'], ['name' => 'IT', 'is_active' => true]);

    return User::factory()->create(['role_id' => Role::factory()->create(['department_id' => $it->id])->id]);
}

function nonItUser(): User
{
    $hrd = Department::firstOrCreate(['code' => 'hrd'], ['name' => 'HRD', 'is_active' => true]);

    return User::factory()->create(['role_id' => Role::factory()->create(['department_id' => $hrd->id])->id]);
}

test('IT user defaults to the izin tab and loads both summaries', function () {
    Volt::actingAs(itUser())
        ->test('izin.widget.report-izin')
        ->assertSet('canViewIzin', true)
        ->assertSet('tab', 'izin')
        ->assertSet('izinTotal', 10)
        ->assertSet('izinApproved', 5)
        ->assertSet('izinRejected', 2)
        ->assertSet('spdTotal', 5)
        ->assertSee('Ringkasan Izin');
});

test('IT user can switch to the spd tab', function () {
    Volt::actingAs(itUser())
        ->test('izin.widget.report-izin')
        ->call('setTab', 'spd')
        ->assertSet('tab', 'spd')
        ->assertSee('Ringkasan SPD')
        ->assertSet('spdApproved', 3)
        ->assertSet('spdWaiting', 1);
});

test('IT user falls back to izin on an invalid tab', function () {
    Volt::actingAs(itUser())
        ->test('izin.widget.report-izin')
        ->call('setTab', 'bogus')
        ->assertSet('tab', 'izin');
});

test('non-IT user only sees the spd summary', function () {
    Volt::actingAs(nonItUser())
        ->test('izin.widget.report-izin')
        ->assertSet('canViewIzin', false)
        ->assertSet('tab', 'spd')
        ->assertSet('spdTotal', 5)
        ->assertSet('izinTotal', 0) // izin data is never fetched for non-IT
        ->assertSee('Ringkasan SPD')
        ->assertDontSee('Ringkasan Izin');
});

test('non-IT user cannot switch to the izin tab', function () {
    Volt::actingAs(nonItUser())
        ->test('izin.widget.report-izin')
        ->call('setTab', 'izin')
        ->assertSet('tab', 'spd');
});

test('switching tabs dispatches the matching group to the category widget', function () {
    Volt::actingAs(itUser())
        ->test('izin.widget.report-izin')
        ->call('setTab', 'spd')
        ->assertDispatched('widget-pengajuan');
});
