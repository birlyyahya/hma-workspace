<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function fakeSpectechApi(array $items = []): void
{
    Http::fake([
        '*activity-categories/search*' => Http::response([
            'status' => 200,
            'data' => $items,
            'pagination' => [],
        ], 200),
        '*activity-categories/*' => Http::response(['status' => 200, 'data' => []], 200),
        '*activity-categories' => Http::response(['status' => 201, 'data' => []], 201),
    ]);
}

function spectechItem(): array
{
    return [
        'id' => 5,
        'name' => 'Switch Cisco',
        'qty_total' => 3,
        'qty_recived' => 1,
        'total_nominal' => 9000000,
        'qty_nominal' => 3000000,
        'percentage' => 33,
        'note' => 'Catatan lama',
        'images' => [],
        'type' => 'hardware',
    ];
}

test('create forwards the note to the spectech API', function () {
    fakeSpectechApi();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', ['totalproject' => 100000000, 'id' => 1, 'progress' => 0])
        ->set('form.name', 'Kabel UTP')
        ->set('form.quantity', 4)
        ->set('form.price', '500.000')
        ->set('form.notes', 'Kabel cadangan gudang')
        ->call('create')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'activity-categories')
        && ! str_contains($request->url(), 'search')
        && $request['note'] === 'Kabel cadangan gudang');
});

test('editing prefills the existing note and price', function () {
    fakeSpectechApi([spectechItem()]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', ['totalproject' => 100000000, 'id' => 1, 'progress' => 0])
        ->call('editSpectech', 5)
        ->assertSet('form.notes', 'Catatan lama')
        ->assertSet('form.price', 9000000)
        ->assertSet('form.idUpdate', 5);
});

test('update forwards the edited note to the spectech API', function () {
    fakeSpectechApi([spectechItem()]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', ['totalproject' => 100000000, 'id' => 1, 'progress' => 0])
        ->call('editSpectech', 5)
        ->set('form.notes', 'Catatan baru')
        ->call('update')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'activity-categories/5')
        && $request['note'] === 'Catatan baru');
});
