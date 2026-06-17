<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function fakeSpectechSearch(array $items = []): void
{
    Http::fake([
        '*activity-categories/search*' => Http::response([
            'status' => 200,
            'data' => $items,
            'pagination' => [],
        ], 200),
        '*activity-categories' => Http::response([
            'status' => 201,
            'data' => [
                'id' => 99,
                'name' => 'Lisensi Office',
                'qty_total' => 2,
                'qty_recived' => 0,
                'total_nominal' => 2000000,
                'qty_nominal' => 1000000,
                'percentage' => 0,
                'note' => '',
                'images' => [],
                'type' => 'software',
            ],
        ], 201),
    ]);
}

test('spectech tab loads its own data from the API on mount', function () {
    fakeSpectechSearch([
        [
            'id' => 5,
            'name' => 'Switch Cisco',
            'qty_total' => 3,
            'qty_recived' => 1,
            'total_nominal' => 9000000,
            'qty_nominal' => 3000000,
            'percentage' => 33,
            'note' => '',
            'images' => [],
            'type' => 'hardware',
        ],
    ]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 42,
        'progress' => 0,
    ])
        ->assertCount('spectech', 1)
        ->assertSee('Switch Cisco');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'activity-categories/search')
        && (int) $request['project_id'] === 42);
});

test('create forwards the selected type to the spectech API', function () {
    fakeSpectechSearch();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->set('form.type', 'software')
        ->set('form.name', 'Lisensi Office')
        ->set('form.quantity', 2)
        ->set('form.price', '2.000.000')
        ->call('create')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'activity-categories')
        && ! str_contains($request->url(), 'search')
        && $request['type'] === 'software');
});

test('type is required and must be hardware or software', function () {
    fakeSpectechSearch();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->set('form.type', 'firmware')
        ->set('form.name', 'Item')
        ->set('form.quantity', 1)
        ->set('form.price', '1.000')
        ->call('create')
        ->assertHasErrors(['form.type']);
});
