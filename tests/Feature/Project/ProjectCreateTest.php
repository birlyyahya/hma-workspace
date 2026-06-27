<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Masmerise\Toaster\Toaster;

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

function fillRequiredProjectFields($component, int $leaderId)
{
    return $component
        ->set('name', 'Proyek Pengujian')
        ->set('code', 'P99')
        ->set('contract_number', '008')
        ->set('contract_date', '2026-01-01')
        ->set('client', 'Kejaksaan Agung')
        ->set('ppk', 'Nanang Suherman')
        ->set('value', '8000000000')
        ->set('start_date', '2026-02-01')
        ->set('end_date', '2026-03-01')
        ->set('company_id', '1')
        ->set('project_leader_id', (string) $leaderId);
}

test('empty maintenance date is omitted from the payload', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $leader = User::factory()->create();
    fakeCreateProject();

    $component = fillRequiredProjectFields(
        Volt::actingAs($admin)->test('project.project-create'),
        $leader->id
    );

    $component->call('store')->assertHasNoErrors();

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), 'projects')) {
            return false;
        }

        $data = $request->data();

        return ! array_key_exists('maintenance_date', $data)
            && $data['contract_date'] === '2026-01-01'
            && $data['value'] === 8000000000
            && $data['start_date'] === '2026-02-01'
            && $data['end_date'] === '2026-03-01';
    });
});

test('filled maintenance date is forwarded to the api', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $leader = User::factory()->create();
    fakeCreateProject();

    $component = fillRequiredProjectFields(
        Volt::actingAs($admin)->test('project.project-create'),
        $leader->id
    )->set('maintenance_date', '2026-04-01');

    $component->call('store')->assertHasNoErrors();

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), 'projects')) {
            return false;
        }

        return $request->data()['maintenance_date'] === '2026-04-01';
    });
});

test('required fields are validated', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    fakeCreateProject();

    Volt::actingAs($admin)
        ->test('project.project-create')
        ->call('store')
        ->assertHasErrors(['name', 'code', 'contract_date', 'client', 'ppk', 'value', 'start_date', 'end_date']);
});

test('field-level errors from the api are shown on the fields and in the toaster', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $leader = User::factory()->create();
    Toaster::fake();

    Http::fake([
        '*projects' => Http::response([
            'status' => 400,
            'message' => 'Gagal membuat proyek',
            'data' => [],
            'pagination' => [],
            'errors' => [
                'name' => ['Nama proyek sudah ada.'],
            ],
        ], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    $component = fillRequiredProjectFields(
        Volt::actingAs($admin)->test('project.project-create'),
        $leader->id
    )->set('maintenance_date', '2026-04-01');

    $component->call('store')
        ->assertHasErrors('name')
        ->assertNoRedirect()
        ->assertSee('Nama proyek sudah ada.');

    Toaster::assertDispatched('Nama proyek sudah ada.');
});

test('company options are shaped as value/label pairs for the search-select', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);

    Http::fake([
        '*companies*' => Http::response([
            'data' => [
                ['id' => 7, 'name' => 'PT Hana Tekindo'],
                ['id' => 8, 'name' => 'CV Mitra Karya'],
            ],
        ], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    $options = Volt::actingAs($admin)
        ->test('project.project-create')
        ->instance()
        ->companyOptions;

    expect($options)->toBe([
        ['value' => 7, 'label' => 'PT Hana Tekindo'],
        ['value' => 8, 'label' => 'CV Mitra Karya'],
    ]);
});

test('leader options exclude restricted roles and are shaped for the search-select', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $leader = User::factory()->create(['name' => 'Siti Aminah', 'role_id' => Role::factory()->create(['id' => 5])->id]);
    $restricted = User::factory()->create(['name' => 'Admin Tersembunyi', 'role_id' => Role::factory()->create(['id' => 2])->id]);
    fakeCreateProject();

    $options = Volt::actingAs($admin)
        ->test('project.project-create')
        ->instance()
        ->leaderOptions;

    expect(collect($options)->pluck('value'))->toContain($leader->id)
        ->and(collect($options)->pluck('value'))->not->toContain($restricted->id)
        ->and(collect($options)->firstWhere('value', $leader->id)['label'])->toBe('Siti Aminah');
});
