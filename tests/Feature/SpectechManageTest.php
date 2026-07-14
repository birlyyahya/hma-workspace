<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('adding a draft queues it locally without hitting the API', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 42])
        ->set('draftType', 'software')
        ->set('draftName', 'Lisensi Office')
        ->set('draftQuantity', 2)
        ->set('draftPrice', '2.000.000')
        ->call('addDraft')
        ->assertHasNoErrors()
        ->assertCount('drafts', 1)
        ->assertSet('draftName', '')
        ->assertSet('draftType', 'software');

    Http::assertNothingSent();
});

test('draft requires name, quantity and price', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 1])
        ->call('addDraft')
        ->assertHasErrors(['draftName', 'draftQuantity', 'draftPrice'])
        ->assertCount('drafts', 0);
});

test('a queued draft can be removed', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    $component = Volt::test('project.components.project-spectech-manage', ['id' => 1])
        ->set('draftName', 'Switch Cisco')
        ->set('draftQuantity', 3)
        ->set('draftPrice', '9.000.000')
        ->call('addDraft')
        ->assertCount('drafts', 1);

    $uid = $component->get('drafts')[0]['uid'];

    $component->call('removeDraft', $uid)
        ->assertCount('drafts', 0);
});

test('save sends all drafts to the bulk endpoint and clears the queue', function () {
    Http::fake([
        '*spekteks/bulkCreate' => Http::response(['status' => 201], 201),
    ]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 42])
        ->set('draftName', 'Switch Cisco')
        ->set('draftQuantity', 3)
        ->set('draftPrice', '9.000.000')
        ->set('draftDetail', '<ul><li>24 port</li></ul>')
        ->call('addDraft')
        ->set('draftName', 'Lisensi Office')
        ->set('draftType', 'software')
        ->set('draftQuantity', 2)
        ->set('draftPrice', '2.000.000')
        ->call('addDraft')
        ->assertCount('drafts', 2)
        ->call('save')
        ->assertCount('drafts', 0)
        ->assertDispatched('spectechSaved');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'spekteks/bulkCreate')
        && count($request->data()) === 2
        && (int) $request->data()[0]['project_id'] === 42
        && $request->data()[0]['type'] === 'hardware'
        && $request->data()[0]['detail'] === '<ul><li>24 port</li></ul>'
        && (int) $request->data()[0]['qty_recived'] === 0
        && $request->data()[1]['type'] === 'software');
});

test('draft detail is queued and reset after adding', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    $component = Volt::test('project.components.project-spectech-manage', ['id' => 1])
        ->set('draftName', 'Server Rack')
        ->set('draftQuantity', 1)
        ->set('draftPrice', '1.000.000')
        ->set('draftDetail', '<p>RAM 16GB</p>')
        ->call('addDraft')
        ->assertHasNoErrors()
        ->assertSet('draftDetail', null);

    expect($component->get('drafts')[0]['detail'])->toBe('<p>RAM 16GB</p>');
});

test('save does nothing when the queue is empty', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 1])
        ->call('save')
        ->assertNotDispatched('spectechSaved');

    Http::assertNothingSent();
});

test('importParsed sends the frontend-parsed rows to the bulk endpoint', function () {
    Http::fake([
        '*spekteks/bulkCreate' => Http::response(['status' => 201], 201),
    ]);

    $this->actingAs(User::factory()->create());

    $rows = [
        ['name' => 'Switch Cisco', 'type' => 'hardware', 'qty_total' => 3, 'total_nominal' => 9000000, 'note' => '', 'detail' => '48 port PoE'],
        ['name' => 'Lisensi Office', 'type' => 'software', 'qty_total' => 2, 'total_nominal' => 2000000, 'note' => 'Volume'],
    ];

    Volt::test('project.components.project-spectech-manage', ['id' => 7])
        ->call('importParsed', $rows)
        ->assertReturned(true)
        ->assertDispatched('spectechSaved')
        ->assertDispatched('excel-import-reset');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'spekteks/bulkCreate')
        && count($request->data()) === 2
        && (int) $request->data()[0]['project_id'] === 7
        && $request->data()[0]['type'] === 'hardware'
        && $request->data()[0]['detail'] === '48 port PoE'
        && (int) $request->data()[0]['qty_recived'] === 0
        && $request->data()[1]['name'] === 'Lisensi Office'
        && $request->data()[1]['detail'] === null);
});

test('importParsed rejects invalid rows without hitting the API', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    $rows = [
        ['name' => '', 'type' => 'firmware', 'qty_total' => 0, 'total_nominal' => -5, 'note' => ''],
    ];

    Volt::test('project.components.project-spectech-manage', ['id' => 7])
        ->call('importParsed', $rows)
        ->assertReturned(false)
        ->assertNotDispatched('spectechSaved');

    Http::assertNothingSent();
});

test('importParsed rejects an empty row set', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 7])
        ->call('importParsed', [])
        ->assertReturned(false);

    Http::assertNothingSent();
});
