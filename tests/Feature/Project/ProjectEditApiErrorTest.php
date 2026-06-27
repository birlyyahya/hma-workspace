<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Masmerise\Toaster\Toaster;

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Cache::flush();
});

test('field-level errors from the api are shown on the matching fields', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    User::factory()->create(['name' => 'Siti Aminah', 'role_id' => Role::factory()->create(['id' => 5])->id]);
    Toaster::fake();

    Http::fake([
        '*companies*' => Http::response([
            'data' => [['id' => 7, 'name' => 'PT Hana Tekindo']],
        ], 200),
        '*projects/1' => function ($request) {
            if ($request->method() === 'PATCH') {
                return Http::response([
                    'status' => 400,
                    'message' => 'Gagal mengubah data proyek',
                    'data' => [],
                    'pagination' => [],
                    'errors' => ['name' => ['Nama proyek sudah ada.']],
                ], 200);
            }

            return Http::response([
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
                    'maintenance_date' => '2026-04-01',
                    'project_leader_id' => 5,
                    'company_id' => 7,
                    'support_teams' => [],
                ]],
            ], 200);
        },
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    Volt::actingAs($admin)
        ->test('project.project-edit', ['id' => 1])
        ->call('update')
        ->assertHasErrors('name')
        ->assertNoRedirect()
        ->assertSee('Nama proyek sudah ada.');

    Toaster::assertDispatched('Nama proyek sudah ada.');
});
