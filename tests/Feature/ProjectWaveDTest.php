<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('files-tabs finalizeChunkUpload posts the doc through ProjectWriter', function () {
    Http::fake([
        '*admin-doc-categories*' => Http::response(['status' => 200, 'data' => []], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [], 'pagination' => []], 200),
        '*admin-docs' => Http::response(['status' => 201, 'data' => ['id' => 5]], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-files-tabs', ['id' => 68])
        ->call('finalizeChunkUpload', [
            'title' => 'Dokumen Kontrak Final',
            'admin_doc_category_id' => 1,
            'filename' => 'kontrak.pdf',
            'original_name' => 'kontrak.pdf',
        ]);

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/admin-docs'));
});

test('spectech-tabs deleteSpectech sends the DELETE through ProjectWriter', function () {
    Http::fake([
        '*spekteks/search*' => Http::response(['status' => 200, 'data' => [
            [
                'id' => 3, 'name' => 'Spektek A', 'type' => 'hardware',
                'qty_total' => 1, 'qty_received' => 0, 'total_nominal' => 1000,
                'qty_nominal' => 1000, 'progress_percentage' => 0, 'note' => '',
            ],
        ]], 200),
        '*spekteks/3' => Http::response([], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-tabs', ['id' => 100])
        ->set('deletingId', 3)
        ->set('deletingName', 'Spektek A')
        ->call('deleteSpectech');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/spekteks/3'));
});

test('spectech-manage save posts the bulk payload through ProjectWriter', function () {
    Http::fake([
        '*spekteks/bulk' => Http::response(['status' => 200], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-spectech-manage', ['id' => 100])
        ->set('drafts', [
            ['uid' => 'd1', 'name' => 'Item A', 'quantity' => 2, 'price' => 5000, 'type' => 'hardware', 'note' => ''],
        ])
        ->call('save');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/spekteks/bulk')
        && $request['project_id'] === 100);
});
