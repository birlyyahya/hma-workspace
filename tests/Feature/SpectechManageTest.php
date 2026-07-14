<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
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
        && (int) $request->data()[0]['qty_recived'] === 0
        && $request->data()[1]['type'] === 'software');
});

test('save does nothing when the queue is empty', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 1])
        ->call('save')
        ->assertNotDispatched('spectechSaved');

    Http::assertNothingSent();
});

test('import uploads an excel file to the import endpoint', function () {
    Http::fake([
        '*spekteks/import' => Http::response(['status' => 200], 200),
    ]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 7])
        ->set('importFile', UploadedFile::fake()->createWithContent('spektek.csv', "name,qty_total,total_nominal,type\nSwitch,3,9000000,hardware\n"))
        ->call('import')
        ->assertHasNoErrors()
        ->assertDispatched('spectechSaved');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'spekteks/import'));
});

test('import rejects non-excel files', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 7])
        ->set('importFile', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->call('import')
        ->assertHasErrors(['importFile']);

    Http::assertNothingSent();
});
