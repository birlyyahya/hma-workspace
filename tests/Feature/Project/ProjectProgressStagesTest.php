<?php

use App\Models\ProjectFileSize;
use App\Models\User;
use App\Services\ProjectCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-10');
});

afterEach(function () {
    Carbon::setTestNow();
});

function fakeProgressEndpoints(int $userId): void
{
    Http::fake([
        '*/timelines/search*' => Http::response(['data' => [
            ['id' => 1, 'title' => 'TANDA TANGAN KONTRAK', 'start_date' => '2026-05-01', 'end_date' => '2026-06-30', 'notes' => 'Kontrak oke'],
            ['id' => 2, 'title' => 'PEMERIKSAAN', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'notes' => null],
            ['id' => 3, 'title' => 'PELATIHAN', 'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'notes' => null],
            ['id' => 4, 'title' => 'DISTRIBUSI', 'start_date' => '2026-05-01', 'end_date' => '2026-06-30', 'notes' => null],
        ]], 200),
        '*/global/dar/list*' => Http::response(['data' => [
            ['id' => 10, 'user_id' => $userId, 'activity' => 'Tanda tangan selesai', 'date' => '2026-06-01 09:00:00', 'status' => 4, 'project_category_id' => 1],
            ['id' => 11, 'user_id' => $userId, 'activity' => 'Distribusi berjalan', 'date' => '2026-06-01 09:00:00', 'status' => 1, 'project_category_id' => 4],
        ]], 200),
        '*/admin-docs/search*' => Http::response(['data' => [
            ['id' => 100, 'title' => 'Kontrak.pdf', 'files' => ['size' => '1 MB'], 'created_at' => '2026-05-15 10:00:00'],
        ]], 200),
    ]);
}

test('progressStages maps timelines, activities, and documents with derived status', function () {
    $user = User::factory()->create(['name' => 'Birly']);
    fakeProgressEndpoints($user->id);

    ProjectFileSize::query()->create(['project_id' => 69, 'doc_id' => 100, 'size_bytes' => 1048576]);

    $stages = (new ProjectCache('http://api.test'))->progressStages(69);

    expect($stages)->toHaveCount(4);

    $byTitle = collect($stages)->keyBy('title');

    // Lewat + semua aktivitas CLOSED → done
    expect($byTitle['TANDA TANGAN KONTRAK'])
        ->status->toBe('done')
        ->icon->toBe('document-check')
        ->date->toBe('30 Jun 2026')
        ->signals->toBe(['1 Dokumen', '1/1 Task DAR selesai'])
        ->notes->toBe('Kontrak oke');
    expect($byTitle['TANDA TANGAN KONTRAK']['activities'][0]['user'])->toBe('Birly');
    expect($byTitle['TANDA TANGAN KONTRAK']['documents'])->toBe([['name' => 'Kontrak.pdf', 'size' => 1048576]]);

    // Hari ini di dalam rentang → current
    expect($byTitle['PEMERIKSAAN'])->status->toBe('current')->date->toBeNull();

    // Belum mulai → upcoming
    expect($byTitle['PELATIHAN'])->status->toBe('upcoming');

    // Lewat tapi ada aktivitas belum CLOSED → pending. Dokumen "Kontrak.pdf"
    // TIDAK bocor ke sini karena judul tahap "DISTRIBUSI" tak cocok dengan dokumen.
    expect($byTitle['DISTRIBUSI'])
        ->status->toBe('pending')
        ->documents->toBe([])
        ->signals->toBe(['0/1 Task DAR selesai']);
});

test('progressStages tidak membocorkan dokumen ke tahap lain karena nama project yang sama', function () {
    $user = User::factory()->create(['name' => 'Birly']);

    Http::fake([
        '*/timelines/search*' => Http::response(['data' => [
            ['id' => 1, 'title' => 'TANDA TANGAN KONTRAK', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'notes' => null],
            ['id' => 2, 'title' => 'IMPLEMENTASI', 'start_date' => '2026-06-01', 'end_date' => '2026-07-31', 'notes' => null],
        ]], 200),
        // Aktivitas DAR di tahap IMPLEMENTASI menyebut nama paket "Assesment Center"
        // yang juga muncul di judul dokumen kontrak — inilah sumber kebocoran lama.
        '*/global/dar/list*' => Http::response(['data' => [
            ['id' => 20, 'user_id' => $user->id, 'activity' => 'Survey Lokasi Server Paket B1 Assesment Center', 'date' => '2026-06-20 09:00:00', 'status' => 1, 'project_category_id' => 2],
        ]], 200),
        '*/admin-docs/search*' => Http::response(['data' => [
            ['id' => 100, 'title' => 'KONTRAK PT WIJAYA MUNCUL MAKMUR ASSESMENT CENTER', 'created_at' => '2026-05-20 10:00:00'],
        ]], 200),
    ]);

    $byTitle = collect((new ProjectCache('http://api.test'))->progressStages(69))->keyBy('title');

    expect($byTitle['TANDA TANGAN KONTRAK']['documents'])->toHaveCount(1)
        ->and($byTitle['TANDA TANGAN KONTRAK']['documents'][0]['name'])->toBe('KONTRAK PT WIJAYA MUNCUL MAKMUR ASSESMENT CENTER')
        ->and($byTitle['IMPLEMENTASI']['documents'])->toBe([]);
});

test('progressStages sorts stages by earliest start date', function () {
    $user = User::factory()->create();
    fakeProgressEndpoints($user->id);

    $starts = collect((new ProjectCache('http://api.test'))->progressStages(69))->pluck('range');

    expect($starts->first())->toStartWith('1 Mei')
        ->and($starts->last())->toStartWith('1 Agt');
});

test('progressStages returns empty array when project has no timelines', function () {
    Http::fake(['*/timelines/search*' => Http::response(['data' => []], 200)]);

    expect((new ProjectCache('http://api.test'))->progressStages(69))->toBe([]);
});

test('progressSummary aggregates counts and percent from stages', function () {
    $user = User::factory()->create();
    fakeProgressEndpoints($user->id);

    $summary = (new ProjectCache('http://api.test'))->progressSummary(69);

    expect($summary)
        ->total->toBe(4)
        ->done->toBe(1)
        ->current->toBe(1)
        ->upcoming->toBe(1)
        ->pending->toBe(1)
        ->percent->toBe(25)
        ->current_title->toBe('PEMERIKSAAN');
});

test('progressSummary is zeroed for a project without timelines', function () {
    Http::fake(['*/timelines/search*' => Http::response(['data' => []], 200)]);

    expect((new ProjectCache('http://api.test'))->progressSummary(69))
        ->total->toBe(0)
        ->percent->toBe(0)
        ->current_title->toBeNull();
});

test('overview tab renders progress stepper from progressStages', function () {
    $user = User::factory()->create();

    $this->mock(ProjectCache::class, function ($mock) {
        $mock->shouldReceive('spectechFor')->andReturn([]);
        $mock->shouldReceive('progressStages')->andReturn([
            ['key' => 'a', 'title' => 'Tahap Kontrak', 'icon' => 'calendar', 'status' => 'done', 'date' => '1 Jun 2026', 'range' => '1 Mei – 30 Jun 2026', 'signals' => [], 'activities' => [], 'documents' => [], 'notes' => null],
            ['key' => 'b', 'title' => 'Tahap Pemeriksaan', 'icon' => 'calendar', 'status' => 'current', 'date' => null, 'range' => '1 Jul – 31 Jul 2026', 'signals' => [], 'activities' => [], 'documents' => [], 'notes' => null],
        ]);
    });

    Livewire::actingAs($user)
        ->test('project.components.project-overview-tabs', ['project' => projectPayload($user->id)])
        ->assertOk()
        ->assertSee('Tahap Kontrak')
        ->assertSee('Tahap Pemeriksaan')
        ->assertSee('1 dari 2 tahap selesai')
        ->assertDontSee('Belum ada tahapan progress');
});

test('overview tab shows empty state when there are no stages', function () {
    $user = User::factory()->create();

    $this->mock(ProjectCache::class, function ($mock) {
        $mock->shouldReceive('spectechFor')->andReturn([]);
        $mock->shouldReceive('progressStages')->andReturn([]);
    });

    Livewire::actingAs($user)
        ->test('project.components.project-overview-tabs', ['project' => projectPayload($user->id)])
        ->assertOk()
        ->assertSee('Belum ada tahapan progress');
});

test('progress tab shows a guide modal explaining stage statuses', function () {
    $user = User::factory()->create();
    fakeProgressEndpoints($user->id);

    Livewire::actingAs($user)
        ->test('project.components.project-progress-tabs', ['id' => 69])
        ->assertOk()
        ->assertSee('Cara Membaca Tahapan Progress')
        ->assertSee('Bagaimana dokumen masuk ke tahap ini?');
});

function projectPayload(int $leaderId): array
{
    return [
        'id' => 69,
        'code' => 'P69',
        'name' => 'Proyek Uji',
        'status' => 'ON PROGRESS',
        'progress' => 40,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'company_name' => 'PT Uji',
        'company_address' => 'Jl. Uji No. 1',
        'company_director_name' => 'Direktur Uji',
        'project_leader_id' => $leaderId,
        'ppk' => null,
        'support_teams' => [],
        'support_team_internals' => [],
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ];
}
