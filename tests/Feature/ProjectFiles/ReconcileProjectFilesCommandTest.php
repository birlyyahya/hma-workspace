<?php

use App\Services\ProjectFileStorage;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

test('it repoints a BEPM doc to the object that actually moved into a folder', function () {
    Http::fake([
        '*projects/69' => Http::response(['status' => 200, 'data' => [[
            'id' => 69, 'start_date' => '2026-01-01',
        ]]], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 391, 'files' => ['url' => 'projects_docs%2F2026%2F69%2Flaporan.pdf']],
        ]], 200),
        '*admin-docs/*' => Http::response(['status' => 200], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    mock(ProjectFileStorage::class)
        ->shouldReceive('listUnder')->once()->with('projects_docs/2026/69/')
        ->andReturn(['projects_docs/2026/69/Pengajuan Dana/laporan.pdf']);

    $this->artisan('projectfiles:reconcile', ['project' => 69])->assertSuccessful();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/391')
        && $request['file'] === 'projects_docs/2026/69/Pengajuan Dana/laporan.pdf');
});

test('it registers an orphan MinIO object that has no BEPM document', function () {
    Http::fake([
        '*projects/69' => Http::response(['status' => 200, 'data' => [[
            'id' => 69, 'start_date' => '2026-01-01', 'name' => 'Proyek A',
        ]]], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => []], 200),
        '*admin-doc-categories*' => Http::response(['status' => 200, 'data' => [['id' => 7, 'name' => 'Umum']]], 200),
        '*admin-docs' => Http::response(['status' => 201, 'data' => ['id' => 50]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    mock(ProjectFileStorage::class)
        ->shouldReceive('listUnder')->once()->with('projects_docs/2026/69/')
        ->andReturn(['projects_docs/2026/69/orphan.pdf']);

    $this->artisan('projectfiles:reconcile', ['project' => 69])->assertSuccessful();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), 'admin-docs')
        && $request['file'] === 'projects_docs/2026/69/orphan.pdf'
        && $request['keyword'] === ['Proyek A', 'orphan']);
});

test('dry-run reports without patching BEPM', function () {
    Http::fake([
        '*projects/69' => Http::response(['status' => 200, 'data' => [[
            'id' => 69, 'start_date' => '2026-01-01',
        ]]], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 391, 'files' => ['url' => 'projects_docs%2F2026%2F69%2Flaporan.pdf']],
        ]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    mock(ProjectFileStorage::class)
        ->shouldReceive('listUnder')->once()
        ->andReturn(['projects_docs/2026/69/Pengajuan Dana/laporan.pdf']);

    $this->artisan('projectfiles:reconcile', ['project' => 69, '--dry-run' => true])->assertSuccessful();

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});
