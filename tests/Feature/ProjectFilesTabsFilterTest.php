<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function fakeProjectDocs(): void
{
    Http::fake([
        '*admin-doc-categories*' => Http::response(['status' => 200, 'data' => []], 200),
        '*admin-docs/search*' => Http::response([
            'status' => 200,
            'data' => [
                ['id' => 1, 'title' => 'Foto Lapangan A', 'created_at' => '2026-06-01 10:00:00', 'files' => ['url' => 'admin_docs/a.jpeg', 'size' => '1 MB']],
                ['id' => 2, 'title' => 'Foto Lapangan B', 'created_at' => '2026-06-02 10:00:00', 'files' => ['url' => 'admin_docs/b.png', 'size' => '1 MB']],
                ['id' => 3, 'title' => 'Foto Lapangan C', 'created_at' => '2026-06-03 10:00:00', 'files' => ['url' => 'admin_docs/c.jpg', 'size' => '1 MB']],
                ['id' => 4, 'title' => 'Kontrak Final Zulu', 'created_at' => '2026-06-04 10:00:00', 'files' => ['url' => 'admin_docs/d.pdf', 'size' => '1 MB']],
            ],
            'pagination' => ['total' => 4],
        ], 200),
    ]);
}

test('photos folder shows jpeg, png, and jpg files', function () {
    fakeProjectDocs();
    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-files-tabs', ['id' => 68])
        ->assertSet('folderCounts.images', 3)
        ->assertSet('folderCounts.pdf', 1)
        ->set('folderKey', 'images')
        ->assertSee('Foto Lapangan A')
        ->assertSee('Foto Lapangan B')
        ->assertSee('Foto Lapangan C')
        ->assertDontSee('Kontrak Final Zulu');
});

test('pdf folder excludes image files', function () {
    fakeProjectDocs();
    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-files-tabs', ['id' => 68])
        ->set('folderKey', 'pdf')
        ->assertSee('Kontrak Final Zulu')
        ->assertDontSee('Foto Lapangan A');
});

test('changing folder resets the pagination limit', function () {
    fakeProjectDocs();
    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-files-tabs', ['id' => 68])
        ->call('loadMore')
        ->assertSet('limit', 16)
        ->set('folderKey', 'images')
        ->assertSet('limit', 8);
});
