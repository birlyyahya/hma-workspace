<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Cache::flush();

    Http::fake([
        '*companies*' => Http::response([
            'data' => [
                ['id' => 7, 'name' => 'PT Hana Tekindo'],
                ['id' => 8, 'name' => 'CV Mitra Karya'],
            ],
        ], 200),
        '*projects/1' => Http::response([
            'status' => 200,
            'data' => [[
                'id' => 1,
                'name' => 'Pembangunan Jembatan',
                'code' => 'P01',
                'contract_number' => '008',
                'contract_date' => '2026-01-01',
                'client' => 'Kejaksaan Agung',
                'ppk' => 'Nanang Suherman',
                'value' => 8000000000,
                'status' => 'ON PROGRESS',
                'start_date' => '2026-02-01',
                'end_date' => '2026-03-01',
                'maintenance_date' => null,
                'project_leader_id' => 5,
                'company_id' => 7,
                'support_teams' => [],
            ]],
        ], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);
});

test('edit form loads the project and renders the reusable search-select options', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    User::factory()->create(['name' => 'Siti Aminah', 'role_id' => Role::factory()->create(['id' => 5])->id]);

    $component = Volt::actingAs($admin)
        ->test('project.project-edit', ['id' => 1])
        ->assertSet('name', 'Pembangunan Jembatan')
        ->assertSet('company_id', '7')
        ->assertSet('value', '8000000000')
        ->assertSee('Perusahaan')
        ->assertSee('Project Leader');

    expect($component->instance()->companyOptions)->toBe([
        ['value' => 7, 'label' => 'PT Hana Tekindo'],
        ['value' => 8, 'label' => 'CV Mitra Karya'],
    ]);
});

test('updating the project forwards the raw numeric value to the api', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    User::factory()->create(['name' => 'Siti Aminah', 'role_id' => Role::factory()->create(['id' => 5])->id]);

    Volt::actingAs($admin)
        ->test('project.project-edit', ['id' => 1])
        ->set('value', '9000000000')
        ->call('update')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), 'projects/1')
            && $request->data()['value'] === 9000000000;
    });
});
