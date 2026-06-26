<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

function userWithProjectViewAll(): User
{
    $role = Role::factory()->create();

    $permission = Permission::query()->firstOrCreate(
        ['name' => 'project.view.all'],
        ['module' => 'project', 'action' => 'view.all', 'label' => 'View all projects'],
    );

    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id]);
}

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Cache::flush();

    $catalog = [
        [
            'id' => 1,
            'name' => 'Pembangunan Jembatan',
            'code' => 'P01',
            'ppk' => 'Nanang Suherman, S.T.MM',
            'client' => 'Kejaksaan Agung',
            'status' => 'ON PROGRESS',
            'progress' => 50,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'project_leader_name' => 'Budi',
        ],
        [
            'id' => 2,
            'name' => 'Sistem Informasi Keuangan',
            'code' => 'P02',
            'ppk' => 'Dewi Lestari',
            'client' => 'Kemenkeu',
            'status' => 'WAITING',
            'progress' => 0,
            'start_date' => '2025-03-01',
            'end_date' => '2025-09-30',
            'project_leader_name' => 'Sari',
        ],
    ];

    Http::fake([
        '*projects/search*' => Http::response([
            'status' => 200,
            'data' => $catalog,
            'pagination' => ['total' => 2, 'last_page' => 1, 'current_page' => 1],
        ], 200),
    ]);
});

test('search matches by ppk name', function () {
    $user = userWithProjectViewAll();

    Volt::actingAs($user)
        ->test('project.project-cards')
        ->set('search', 'Nanang')
        ->call('applyFilters')
        ->assertSee('Pembangunan Jembatan')
        ->assertDontSee('Sistem Informasi Keuangan');
});

test('search matches by project name', function () {
    $user = userWithProjectViewAll();

    Volt::actingAs($user)
        ->test('project.project-cards')
        ->set('search', 'Keuangan')
        ->call('applyFilters')
        ->assertSee('Sistem Informasi Keuangan')
        ->assertDontSee('Pembangunan Jembatan');
});

test('search matches by project code', function () {
    $user = userWithProjectViewAll();

    Volt::actingAs($user)
        ->test('project.project-cards')
        ->set('search', 'P02')
        ->call('applyFilters')
        ->assertSee('Sistem Informasi Keuangan')
        ->assertDontSee('Pembangunan Jembatan');
});

test('confirming delete stages the pending project for the modal', function () {
    $user = userWithProjectViewAll();

    Volt::actingAs($user)
        ->test('project.project-cards')
        ->call('confirmDelete', 1)
        ->assertSet('pendingDeleteId', 1)
        ->assertSet('pendingDeleteName', 'Pembangunan Jembatan');
});

test('deleting without a pending project is a no-op', function () {
    $user = userWithProjectViewAll();

    Volt::actingAs($user)
        ->test('project.project-cards')
        ->call('deleteProject');

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

test('deleting the pending project sends the delete request and clears the pending state', function () {
    $user = userWithProjectViewAll();

    Volt::actingAs($user)
        ->test('project.project-cards')
        ->call('confirmDelete', 1)
        ->call('deleteProject')
        ->assertSet('pendingDeleteId', null)
        ->assertSet('pendingDeleteName', '');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'projects/1'));
});

test('a user without project.view.all sees the forbidden state instead of projects', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('project.project-cards')
        ->assertSet('forbidden', true)
        ->assertSee('Akses Ditolak')
        ->assertDontSee('Pembangunan Jembatan');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'projects/search'));
});
