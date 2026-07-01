<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('timeline gantt loads timelines through ProjectCache', function () {
    Http::fake(['*timelines/search*' => Http::response([
        'status' => 200,
        'data' => [
            ['id' => 1, 'title' => 'Fase 1', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01'],
        ],
    ], 200)]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-timeline-gantt', ['id' => 401])
        ->assertSet('timelines', [
            ['id' => 1, 'title' => 'Fase 1', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01'],
        ])
        ->assertStatus(200);
});

test('project preview sets the project on success', function () {
    Http::fake(['*projects/402*' => Http::response([
        'status' => 200,
        'data' => [[
            'id' => 402,
            'name' => 'Proyek Alpha',
            'code' => 'PA-001',
            'client' => 'PT Klien',
            'ppk' => 'PPK X',
            'value' => 1000000,
            'status' => 'ONPROGRESS',
            'progress' => 50,
            'contract_number' => 'CN-1',
            'contract_date' => '2026-01-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'maintenance_date' => null,
            'updated_at' => '2026-06-01 10:00:00',
            'project_leader_name' => 'Budi',
            'company_name' => 'PT Vendor',
            'company_address' => 'Jl. Mawar',
            'company_director_name' => 'Direktur',
            'company_director_phone' => '0800',
            'company_director_signature' => '/storage/ttd.png',
            'support_teams' => [],
            'support_team_internals' => [],
            'specktech' => [],
        ]],
    ], 200)]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.project-preview', ['id' => 402])
        ->assertSet('error', null)
        ->assertSet('loading', false)
        ->assertSet('project.name', 'Proyek Alpha');
});

test('project preview shows not-found error when data is empty', function () {
    Http::fake(['*projects/403*' => Http::response(['status' => 200, 'data' => []], 200)]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.project-preview', ['id' => 403])
        ->assertSet('error', 'Project tidak ditemukan.')
        ->assertSet('loading', false);
});
