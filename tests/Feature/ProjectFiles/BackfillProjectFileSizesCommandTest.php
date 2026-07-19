<?php

use App\Models\ProjectFileSize;
use App\Services\ProjectFileStorage;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

function fakeBepmForSizeBackfill(): void
{
    Http::fake([
        '*projects/5' => Http::response(['status' => 200, 'data' => [[
            'id' => 5, 'start_date' => '2026-01-01', 'project_leader_id' => 1, 'support_team_internals' => [],
        ]]], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects_docs/2026/5/laporan.pdf']],
            ['id' => 2, 'title' => 'kontrak', 'files' => ['url' => 'projects_docs%2F2026%2F5%2FKontrak%2Fkontrak.pdf']],
            ['id' => 3, 'title' => 'hilang', 'files' => ['url' => 'projects_docs/2026/5/tidak-ada.pdf']],
        ]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);
}

test('it records sizes from a single MinIO listing per project', function () {
    fakeBepmForSizeBackfill();

    mock(ProjectFileStorage::class)
        ->shouldReceive('sizesUnder')
        ->once()
        ->with('projects_docs/2026/5/')
        ->andReturn([
            'projects_docs/2026/5/laporan.pdf' => 1024,
            'projects_docs/2026/5/Kontrak/kontrak.pdf' => 4096,
        ]);

    $this->artisan('projectfiles:backfill-sizes', ['project' => 5])
        ->expectsOutputToContain('dicatat=2 hilang=1')
        ->assertSuccessful();

    expect(ProjectFileSize::query()->where('doc_id', 1)->value('size_bytes'))->toBe(1024)
        ->and(ProjectFileSize::query()->where('doc_id', 2)->value('size_bytes'))->toBe(4096)
        ->and(ProjectFileSize::query()->where('doc_id', 3)->exists())->toBeFalse();
});

test('a dry run reports the plan without writing sizes', function () {
    fakeBepmForSizeBackfill();

    mock(ProjectFileStorage::class)
        ->shouldReceive('sizesUnder')
        ->once()
        ->andReturn(['projects_docs/2026/5/laporan.pdf' => 1024]);

    $this->artisan('projectfiles:backfill-sizes', ['project' => 5, '--dry-run' => true])
        ->assertSuccessful();

    expect(ProjectFileSize::query()->count())->toBe(0);
});

test('it refuses to run without a project id or --all', function () {
    $this->artisan('projectfiles:backfill-sizes')
        ->expectsOutputToContain('Sebutkan ID project atau pakai --all.')
        ->assertFailed();
});
