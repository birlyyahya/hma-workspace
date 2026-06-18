<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(fn () => Livewire::withoutLazyLoading());

function fakeProject(int $leaderId, array $internalUserIds = []): void
{
    Http::fake([
        '*projects/1' => Http::response([
            'status' => 200,
            'data' => [[
                'id' => 1,
                'code' => 'PRJ-1',
                'name' => 'Secret Project',
                'client' => 'ACME',
                'status' => 'ON PROGRESS',
                'value' => 1000000,
                'progress' => 10,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'contract_number' => 'CN-1',
                'contract_date' => '2026-01-01',
                'maintenance_date' => '2027-01-01',
                'ppk' => 'PPK A',
                'company_name' => 'PT Contoh',
                'company_address' => 'Jl. Contoh',
                'company_director_name' => 'Dir',
                'company_director_phone' => '0800',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-02T00:00:00Z',
                'project_leader_id' => $leaderId,
                'support_teams' => [],
                'support_team_internals' => collect($internalUserIds)
                    ->map(fn ($id) => [
                        'id' => $id,
                        'project_id' => 1,
                        'user_id' => $id,
                        'user_name' => 'Member '.$id,
                        'user_username' => 'member'.$id,
                    ])
                    ->all(),
            ]],
        ], 200),
        '*' => Http::response([
            'status' => 200,
            'success' => true,
            'message' => 'ok',
            'data' => [],
            'pagination' => [],
        ], 200),
    ]);
}

test('the project leader can open the project detail', function () {
    $leader = User::factory()->create();
    fakeProject(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.project-show', ['id' => 1])
        ->assertOk()
        ->assertSee('Secret Project');
});

test('the project detail opens when support team data is null', function () {
    $leader = User::factory()->create();

    Http::fake([
        '*projects/1' => Http::response([
            'status' => 200,
            'data' => [[
                'id' => 1,
                'code' => 'PRJ-1',
                'name' => 'Secret Project',
                'client' => 'ACME',
                'status' => 'ON PROGRESS',
                'value' => 1000000,
                'progress' => 10,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'contract_number' => 'CN-1',
                'contract_date' => '2026-01-01',
                'maintenance_date' => '2027-01-01',
                'ppk' => 'PPK A',
                'company_name' => 'PT Contoh',
                'company_address' => 'Jl. Contoh',
                'company_director_name' => 'Dir',
                'company_director_phone' => '0800',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-02T00:00:00Z',
                'project_leader_id' => $leader->id,
                'support_teams' => null,
                'support_team_internals' => null,
            ]],
        ], 200),
        '*' => Http::response([
            'status' => 200,
            'success' => true,
            'message' => 'ok',
            'data' => [],
            'pagination' => [],
        ], 200),
    ]);

    Volt::actingAs($leader)
        ->test('project.project-show', ['id' => 1])
        ->assertOk()
        ->assertSee('Secret Project');
});

test('an internal team member can open the project detail', function () {
    $leader = User::factory()->create();
    $member = User::factory()->create();
    fakeProject(leaderId: $leader->id, internalUserIds: [$member->id]);

    Volt::actingAs($member)
        ->test('project.project-show', ['id' => 1])
        ->assertOk()
        ->assertSee('Secret Project');
});

test('a user with project view-all scope can open any project detail', function () {
    $leader = User::factory()->create();
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    fakeProject(leaderId: $leader->id);

    Volt::actingAs($admin)
        ->test('project.project-show', ['id' => 1])
        ->assertOk()
        ->assertSee('Secret Project');
});

test('an unrelated user sees the forbidden state instead of the project', function () {
    $leader = User::factory()->create();
    $intruder = User::factory()->create();
    fakeProject(leaderId: $leader->id);

    Volt::actingAs($intruder)
        ->test('project.project-show', ['id' => 1])
        ->assertOk()
        ->assertSet('forbidden', true)
        ->assertSet('project', null)
        ->assertSee('Akses Ditolak')
        ->assertDontSee('Secret Project');
});
