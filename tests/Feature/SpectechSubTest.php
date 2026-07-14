<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

/**
 * Sub spektek tersemat di response spekteks/search (with_sub=true), jadi
 * tidak ada fake untuk endpoint baca sub — hanya endpoint tulisnya.
 *
 * @param  array<int, array<string, mixed>>  $subs
 */
function fakeSubSpectechApi(array $subs = []): void
{
    Http::fake([
        '*sub-spekteks/*' => Http::response(['status' => 200, 'data' => []], 200),
        '*sub-spekteks' => Http::response(['status' => 201, 'data' => []], 201),
        '*spekteks/search*' => Http::response(['status' => 200, 'data' => [
            [
                'id' => 5,
                'name' => 'Server Rack',
                'qty_total' => 2,
                'qty_received' => 0,
                'total_nominal' => 40000000,
                'qty_nominal' => 20000000,
                'progress_percentage' => 0,
                'note' => '',
                'detail' => '',
                'images' => [],
                'type' => 'hardware',
                'sub_spekteks' => $subs,
            ],
        ]], 200),
    ]);
}

/**
 * @return array<string, mixed>
 */
function subSpectechItem(): array
{
    return [
        'id' => 2,
        'name' => 'Device Finder',
        'qty_total' => 5,
        'qty_received' => 1,
        'total_nominal' => 4000000,
        'progress_percentage' => 20,
        'type' => 'hardware',
        'spektek_id' => 5,
    ];
}

test('expanding a row shows subs embedded in the spektek payload without extra API hits', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->assertSee('1 sub')
        ->call('toggleExpand', 5)
        ->assertSet('expandedId', 5)
        ->assertSet('showSubForm', false)
        ->assertCount('subItems', 1)
        ->assertSee('Device Finder');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'spekteks/search')
        && str_contains($request->url(), 'with_sub=true'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sub-spekteks/search'));
});

test('expanding the same row again collapses the panel', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('toggleExpand', 5)
        ->call('toggleExpand', 5)
        ->assertSet('expandedId', null)
        ->assertCount('subItems', 0);
});

test('saveSub creates a sub spektek attached to the expanded item', function () {
    fakeSubSpectechApi();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('toggleExpand', 5)
        ->set('subName', 'Kabel Fiber')
        ->set('subQuantity', 10)
        ->set('subPrice', '2000000')
        ->set('subType', 'hardware')
        ->call('saveSub')
        ->assertHasNoErrors()
        ->assertSet('subName', '');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/sub-spekteks')
        && $request['name'] === 'Kabel Fiber'
        && (int) $request['qty_total'] === 10
        && (int) $request['spektek_id'] === 5);
});

test('sub form requires name, quantity and price', function () {
    fakeSubSpectechApi();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('toggleExpand', 5)
        ->call('saveSub')
        ->assertHasErrors(['subName', 'subQuantity', 'subPrice']);

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/sub-spekteks'));
});

test('editSub prefills the inline form and saveSub patches the sub spektek', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('toggleExpand', 5)
        ->call('editSub', 2)
        ->assertSet('showSubForm', true)
        ->assertSet('subName', 'Device Finder')
        ->assertSet('subQuantity', 5)
        ->set('subName', 'Device Finder V2')
        ->call('saveSub')
        ->assertHasNoErrors()
        ->assertSet('subEditId', null);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/sub-spekteks/2')
        && $request['name'] === 'Device Finder V2');
});

test('updateSubQty patches the qty received endpoint and clamps to qty total', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    $component = Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])->call('toggleExpand', 5);

    $component->call('updateSubQty', 2, 3);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/sub-spekteks/2/updateQtyReceived')
        && (int) $request['qty_received'] === 3);

    $component->call('updateSubQty', 2, 99);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sub-spekteks/2/updateQtyReceived')
        && (int) $request['qty_received'] === 5);
});

test('deleteSub sends a DELETE for the selected sub spektek', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('toggleExpand', 5)
        ->call('confirmDeleteSub', 2)
        ->assertSet('deletingSubId', 2)
        ->call('deleteSub')
        ->assertSet('deletingSubId', null);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/sub-spekteks/2'));
});

test('sub form is collapsible and closing it cancels an in-progress edit', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('toggleExpand', 5)
        ->call('toggleSubForm')
        ->assertSet('showSubForm', true)
        ->call('editSub', 2)
        ->call('toggleSubForm')
        ->assertSet('showSubForm', false)
        ->assertSet('subEditId', null)
        ->assertSet('subName', '');
});

test('bulk mode disables row expansion', function () {
    fakeSubSpectechApi();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ])
        ->call('toggleBulkMode')
        ->call('toggleExpand', 5)
        ->assertSet('expandedId', null);
});
