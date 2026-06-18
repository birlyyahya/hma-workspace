<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(fn () => Livewire::withoutLazyLoading());

function fakeCreateProject(): void
{
    Http::fake([
        '*projects' => Http::response([
            'status' => 201,
            'message' => 'created',
            'data' => ['id' => 99],
        ], 201),
        '*' => Http::response([
            'status' => 200,
            'data' => [],
            'pagination' => [],
        ], 200),
    ]);
}

test('empty optional fields are omitted from the payload', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $leader = User::factory()->create();
    fakeCreateProject();

    Volt::actingAs($admin)
        ->test('project.project-create')
        ->set('name', 'Proyek Pengujian')
        ->set('code', 'P99')
        ->set('company_id', '1')
        ->set('project_leader_id', (string) $leader->id)
        ->call('store')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), 'projects')) {
            return false;
        }

        $data = $request->data();

        return ! array_key_exists('contract_date', $data)
            && ! array_key_exists('contract_number', $data)
            && ! array_key_exists('client', $data)
            && ! array_key_exists('ppk', $data)
            && ! array_key_exists('value', $data)
            && ! array_key_exists('start_date', $data)
            && ! array_key_exists('end_date', $data)
            && ! array_key_exists('maintenance_date', $data)
            && $data['name'] === 'Proyek Pengujian'
            && $data['code'] === 'P99';
    });
});

test('filled optional fields are forwarded to the api', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $leader = User::factory()->create();
    fakeCreateProject();

    Volt::actingAs($admin)
        ->test('project.project-create')
        ->set('name', 'Proyek Pengujian')
        ->set('code', 'P99')
        ->set('company_id', '1')
        ->set('project_leader_id', (string) $leader->id)
        ->set('contract_date', '2026-01-01')
        ->set('value', '8000000000')
        ->set('start_date', '2026-02-01')
        ->set('end_date', '2026-03-01')
        ->call('store')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), 'projects')) {
            return false;
        }

        $data = $request->data();

        return $data['contract_date'] === '2026-01-01'
            && $data['value'] === 8000000000
            && $data['start_date'] === '2026-02-01'
            && $data['end_date'] === '2026-03-01';
    });
});
