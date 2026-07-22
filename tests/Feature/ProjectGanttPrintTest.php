<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function fakeProjectAndTimelines(array $project, array $timelines, array $tasks = []): void
{
    Http::fake([
        '*projects/501*' => Http::response(['status' => 200, 'data' => [$project]], 200),
        '*timelines/search*' => Http::response(['status' => 200, 'data' => $timelines], 200),
        '*dar/list*' => Http::response(['data' => $tasks], 200),
    ]);
}

test('a non-member is forbidden from the gantt print page', function () {
    fakeProjectAndTimelines(
        ['id' => 501, 'code' => 'PRJ-501', 'name' => 'Proyek Rahasia', 'project_leader_id' => 999, 'support_team_internals' => []],
        [['id' => 1, 'title' => 'Fase 1', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01']],
    );

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('projects.gantt-print', 501))
        ->assertForbidden();
});

test('a super-admin can view the gantt print page with weekly columns rendered', function () {
    fakeProjectAndTimelines(
        ['id' => 501, 'code' => 'PRJ-501', 'name' => 'Proyek Alpha', 'project_leader_id' => 999, 'support_team_internals' => []],
        [['id' => 1, 'title' => 'Fase 1', 'start_date' => '2026-01-01', 'end_date' => '2026-02-15']],
    );

    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);

    $this->actingAs($admin)
        ->get(route('projects.gantt-print', 501))
        ->assertOk()
        ->assertSee('PRJ-501')
        ->assertSee('Fase 1')
        ->assertSee('Jan 2026')   // month group header
        ->assertSee('M1');         // weekly sub-column
});

test('a dar activity is positioned by its week within the phase', function () {
    fakeProjectAndTimelines(
        ['id' => 501, 'code' => 'PRJ-501', 'name' => 'Proyek Alpha', 'project_leader_id' => 999, 'support_team_internals' => []],
        [['id' => 7, 'title' => 'Fase 1', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']],
        [[
            'id' => 88,
            'activity' => 'Survey Lokasi',
            'project_category_id' => 7,
            'status' => 1,
            'start_date' => '2026-01-15 09:00:00',
            'end_date' => '2026-01-21 17:00:00',
        ]],
    );

    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);

    // 15–21 Jan jatuh di minggu ke-3 (index 2): leading=2, span=1.
    $component = Volt::actingAs($admin)->test('project.gantt-print', ['id' => 501]);

    $rows = $component->instance()->rows;

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['activities'])->toHaveCount(1)
        ->and($rows[0]['activities'][0]['leading'])->toBe(2)
        ->and($rows[0]['activities'][0]['span'])->toBe(1);
});

test('a very long timeline is truncated with an overflow indicator', function () {
    fakeProjectAndTimelines(
        ['id' => 501, 'code' => 'PRJ-501', 'name' => 'Proyek Maintenance', 'project_leader_id' => 999, 'support_team_internals' => []],
        [
            ['id' => 1, 'title' => 'Persiapan', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01'],
            ['id' => 2, 'title' => 'Maintenance', 'start_date' => '2026-01-15', 'end_date' => '2028-01-15'],
        ],
    );

    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);

    Volt::actingAs($admin)
        ->test('project.gantt-print', ['id' => 501])
        ->assertSet('truncated', true)
        ->assertSee('→', false);
});
