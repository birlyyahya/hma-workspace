<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\IzinCache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

/**
 * Buat user dengan role valid (departemend_id wajib non-null pada skema roles).
 *
 * @param  array<string, mixed>  $attrs
 */
function makeUser(array $attrs = []): User
{
    $role = Role::factory()->create(['departemend_id' => 1, 'is_system' => false]);

    return User::factory()->create($attrs + ['role_id' => $role->id]);
}

/**
 * @param  array<int, array<string, mixed>>  $rows
 */
function fakeIzinList(array $rows): void
{
    Http::fake([
        '*/global/izin/list*' => Http::response([
            'success' => true,
            'data' => $rows,
            'total' => count($rows),
        ]),
    ]);
}

/**
 * @return array<string, mixed>
 */
function izinRow(int $id, string $username, string $status = '2', string $startDate = '2026-06-01'): array
{
    return [
        'id' => $id,
        'username' => $username,
        'user_name' => ucfirst($username).' Name',
        'reason' => 'Sakit',
        'status' => $status,
        'status_admin' => $status,
        'status_superadmin' => $status,
        'start_date' => $startDate,
        'created_at' => '2026-06-01 0'.($id % 9).':00:00',
    ];
}

it('caches the full izin list and reuses it across calls', function () {
    fakeIzinList([izinRow(1, 'arif'), izinRow(2, 'budi')]);

    $cache = app(IzinCache::class);

    expect($cache->allIzin()['total'])->toBe(2);
    $cache->allIzin();
    $cache->allIzin();

    Http::assertSentCount(1);
});

it('flushList forces a fresh fetch', function () {
    fakeIzinList([izinRow(1, 'arif')]);

    $cache = app(IzinCache::class);
    $cache->allIzin();
    $cache->flushList();
    $cache->allIzin();

    Http::assertSentCount(2);
});

it('izin-table shows only the current user rows and paginates in-memory from one API call', function () {
    // Username lokal huruf kecil, API campur huruf besar → harus tetap cocok (case-insensitive).
    $user = makeUser(['username' => 'itarif']);

    $rows = collect(range(1, 12))
        ->map(fn ($i) => izinRow($i, 'ITArif'))
        ->push(izinRow(99, 'ITBudi'))
        ->all();

    fakeIzinList($rows);

    $component = Volt::actingAs($user)->test('izin.izin-table')
        ->assertSet('data.total', 12)
        ->assertSet('data.last_page', 2)
        ->assertSet('data.current_page', 1);

    expect(count($component->get('data')['data']))->toBe(10);

    $component->call('goToPage', 2)
        ->assertSet('data.current_page', 2);

    expect(count($component->get('data')['data']))->toBe(2);

    // Pagination & the second page were served from cache, not extra API hits.
    Http::assertSentCount(1);
});

it('izin-table filters by status in-memory', function () {
    $user = makeUser(['username' => 'itarif']);

    fakeIzinList([
        izinRow(1, 'ITArif', '2'),
        izinRow(2, 'ITArif', '3'),
        izinRow(3, 'ITArif', '2'),
    ]);

    Volt::actingAs($user)->test('izin.izin-table')
        ->set('status', '2')
        ->assertSet('data.total', 2);
});

it('spd-list filters by user scope and paginates from the shared cache', function () {
    $user = makeUser();

    Http::fake([
        '*/global/dar/activity/list-spd*' => Http::response([
            'success' => true,
            'data' => [
                ['id' => 1, 'user_id' => $user->id, 'task' => 'A', 'department' => 'IT', 'destination' => 'X', 'address' => 'Y', 'start_date' => '2026-06-01', 'end_date' => '2026-06-02', 'number' => 1, 'is_submitted' => 1, 'is_approved' => 1, 'created_at' => '2026-06-01 09:00:00', 'attachment_url' => null],
                ['id' => 2, 'user_id' => 999, 'task' => 'B', 'department' => 'IT', 'destination' => 'X', 'address' => 'Y', 'start_date' => '2026-06-01', 'end_date' => '2026-06-02', 'number' => 2, 'is_submitted' => 0, 'is_approved' => 0, 'created_at' => '2026-06-01 10:00:00', 'attachment_url' => null],
            ],
        ]),
    ]);

    Volt::actingAs($user)->test('izin.spd-list')
        ->assertSet('list.total', 1)
        ->assertSet('loading', false);

    Http::assertSentCount(1);
});
