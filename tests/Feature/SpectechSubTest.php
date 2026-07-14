<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

/**
 * Sub spektek tersemat di response spekteks/search (with_sub=true), jadi
 * tidak ada fake untuk endpoint baca sub — hanya endpoint tulisnya. Expand
 * baris ditangani Alpine (client-side), tak ada lagi method server toggleExpand.
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

/**
 * @return \Livewire\Features\SupportTesting\Testable
 */
function spectechTabs()
{
    return Volt::test('project.components.project-spectech-tabs', [
        'totalproject' => 100000000,
        'id' => 1,
        'progress' => 0,
    ]);
}

test('embedded subs render (panel + badge) without an extra sub API call', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    spectechTabs()
        ->assertSee('1 sub')
        ->assertSee('Device Finder');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'spekteks/search')
        && str_contains($request->url(), 'with_sub=true'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sub-spekteks/search'));
});

test('openSubForm and closeSubForm toggle the active form for a spektek', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    spectechTabs()
        ->call('openSubForm', 5)
        ->assertSet('activeSubFormId', 5)
        ->call('closeSubForm')
        ->assertSet('activeSubFormId', null)
        ->assertSet('subName', '');
});

test('saveSub creates a sub spektek attached to the given spektek id', function () {
    fakeSubSpectechApi();

    $this->actingAs(User::factory()->create());

    spectechTabs()
        ->call('openSubForm', 5)
        ->set('subName', 'Kabel Fiber')
        ->set('subQuantity', 10)
        ->set('subPrice', '2000000')
        ->set('subType', 'hardware')
        ->call('saveSub', 5)
        ->assertHasNoErrors()
        ->assertSet('subName', '')
        ->assertSet('activeSubFormId', 5);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/sub-spekteks')
        && $request['name'] === 'Kabel Fiber'
        && (int) $request['qty_total'] === 10
        && (int) $request['spektek_id'] === 5);
});

test('sub form requires name and quantity', function () {
    fakeSubSpectechApi();

    $this->actingAs(User::factory()->create());

    spectechTabs()
        ->call('openSubForm', 5)
        ->call('saveSub', 5)
        ->assertHasErrors(['subName', 'subQuantity']);

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/sub-spekteks'));
});

test('editSub prefills the form and saveSub patches the sub spektek', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    spectechTabs()
        ->call('editSub', 5, 2)
        ->assertSet('activeSubFormId', 5)
        ->assertSet('subEditId', 2)
        ->assertSet('subName', 'Device Finder')
        ->assertSet('subQuantity', 5)
        ->set('subName', 'Device Finder V2')
        ->call('saveSub', 5)
        ->assertHasNoErrors()
        ->assertSet('subEditId', null);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/sub-spekteks/2')
        && $request['name'] === 'Device Finder V2');
});

test('updateSubQty patches the qty received endpoint and clamps to qty total', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    $component = spectechTabs();

    $component->call('updateSubQty', 5, 2, 3);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/sub-spekteks/2/updateQtyReceived')
        && (int) $request['qty_received'] === 3);

    $component->call('updateSubQty', 5, 2, 99);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sub-spekteks/2/updateQtyReceived')
        && (int) $request['qty_received'] === 5);
});

test('deleteSub sends a DELETE for the selected sub spektek', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    spectechTabs()
        ->call('confirmDeleteSub', 5, 2)
        ->assertSet('deletingSubId', 2)
        ->assertSet('deletingSubSpektekId', 5)
        ->call('deleteSub')
        ->assertSet('deletingSubId', null)
        ->assertSet('deletingSubSpektekId', null);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/sub-spekteks/2'));
});

test('switching type or bulk mode closes the sub form and dispatches collapse', function () {
    fakeSubSpectechApi([subSpectechItem()]);

    $this->actingAs(User::factory()->create());

    spectechTabs()
        ->call('openSubForm', 5)
        ->assertSet('activeSubFormId', 5)
        ->call('setType', 'software')
        ->assertSet('activeSubFormId', null)
        ->assertDispatched('spektek-collapse');

    spectechTabs()
        ->call('openSubForm', 5)
        ->call('toggleBulkMode')
        ->assertSet('activeSubFormId', null)
        ->assertDispatched('spektek-collapse');
});
