<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

/**
 * @param  array<int, array<string, mixed>>  $items
 */
function fakeSpectechListApi(array $items): void
{
    Http::fake([
        '*activity-categories/search*' => Http::response([
            'status' => 200,
            'data' => $items,
            'pagination' => [],
        ], 200),
        '*activity-categories*' => Http::response([
            'status' => 201,
            'data' => [],
        ], 201),
    ]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function makeSpectechItems(int $count): array
{
    return collect(range(1, $count))->map(fn (int $i): array => [
        'id' => $i,
        'name' => sprintf('Item %03d', $i),
        'qty_total' => $i,
        'qty_recived' => 0,
        'total_nominal' => $i * 1000,
        'qty_nominal' => 1000,
        'percentage' => 0,
        'note' => '',
        'images' => [],
        'type' => 'hardware',
    ])->all();
}

test('items can be sorted by total nominal in both directions', function () {
    fakeSpectechListApi([
        ['id' => 1, 'name' => 'Kabel UTP', 'qty_total' => 1, 'qty_recived' => 0, 'total_nominal' => 3000, 'qty_nominal' => 3000, 'percentage' => 0, 'note' => '', 'images' => [], 'type' => 'hardware'],
        ['id' => 2, 'name' => 'Router Mikrotik', 'qty_total' => 1, 'qty_recived' => 0, 'total_nominal' => 1000, 'qty_nominal' => 1000, 'percentage' => 0, 'note' => '', 'images' => [], 'type' => 'hardware'],
        ['id' => 3, 'name' => 'Switch Cisco', 'qty_total' => 1, 'qty_recived' => 0, 'total_nominal' => 2000, 'qty_nominal' => 2000, 'percentage' => 0, 'note' => '', 'images' => [], 'type' => 'hardware'],
    ]);

    $this->actingAs(User::factory()->create());

    $component = Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ]);

    $component->call('sortByColumn', 'total_nominal')
        ->assertSet('sortBy', 'total_nominal')
        ->assertSet('sortDir', 'asc')
        ->assertSeeInOrder(['Router Mikrotik', 'Switch Cisco', 'Kabel UTP']);

    $component->call('sortByColumn', 'total_nominal')
        ->assertSet('sortDir', 'desc')
        ->assertSeeInOrder(['Kabel UTP', 'Switch Cisco', 'Router Mikrotik']);

    $component->call('sortByColumn', 'total_nominal')
        ->assertSet('sortBy', 'default')
        ->assertSeeInOrder(['Kabel UTP', 'Router Mikrotik', 'Switch Cisco']);
});

test('invalid sort column is ignored', function () {
    fakeSpectechListApi(makeSpectechItems(1));

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('sortByColumn', 'qty_recived')
        ->assertSet('sortBy', 'default');
});

test('long lists are truncated to 25 items and expand via show more', function () {
    fakeSpectechListApi(makeSpectechItems(30));

    $this->actingAs(User::factory()->create());

    $component = Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ]);

    $component->assertSee('Item 025')
        ->assertDontSee('Item 026')
        ->assertSee('Tampilkan lebih banyak (5 tersisa)');

    $component->call('showMore')
        ->assertSee('Item 026')
        ->assertSee('Item 030')
        ->assertDontSee('Tampilkan lebih banyak');
});

test('changing search or tab resets the visible limit', function () {
    fakeSpectechListApi(makeSpectechItems(30));

    $this->actingAs(User::factory()->create());

    $component = Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ]);

    $component->call('showMore')
        ->assertSet('visibleLimit', 50)
        ->set('search', 'Item')
        ->assertSet('visibleLimit', 25);

    $component->call('showMore')
        ->call('setType', 'software')
        ->assertSet('visibleLimit', 25);
});

test('openAdd presets the form type to the active tab', function () {
    fakeSpectechListApi([]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('setType', 'software')
        ->call('openAdd')
        ->assertSet('form.type', 'software');
});

test('create forwards the detail field to the spectech API', function () {
    fakeSpectechListApi([]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->set('form.name', 'Server Rack')
        ->set('form.quantity', 1)
        ->set('form.price', '1.000.000')
        ->set('form.detail', '<ul><li>RAM 16GB DDR5</li><li>SSD 512GB NVMe</li></ul>')
        ->call('create')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'activity-categories')
        && ! str_contains($request->url(), 'search')
        && $request['detail'] === '<ul><li>RAM 16GB DDR5</li><li>SSD 512GB NVMe</li></ul>');
});

test('editing an item populates the detail field on the form', function () {
    $item = makeSpectechItems(1)[0];
    $item['detail'] = '<p>Prosesor Intel i7</p>';

    fakeSpectechListApi([$item]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('editSpectech', 1)
        ->assertSet('form.detail', '<p>Prosesor Intel i7</p>');
});

test('item detail is rendered in the expandable row', function () {
    $item = makeSpectechItems(1)[0];
    $item['detail'] = '<ul><li>RAM 16GB DDR5</li></ul>';

    fakeSpectechListApi([$item]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->assertSee('<li>RAM 16GB DDR5</li>', false)
        ->assertSee('Detail Spesifikasi');
});

test('manage modal opens on the requested tab', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 1])
        ->call('open', 'import')
        ->assertSet('manageTab', 'import');
});

test('manage modal falls back to manual tab for an unknown tab', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 1])
        ->call('open', 'unknown')
        ->assertSet('manageTab', 'manual');
});
