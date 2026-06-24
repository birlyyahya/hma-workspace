<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(fn () => Livewire::withoutLazyLoading());

test('updateTask sends dates in stored format and preserves date so unchanged fields are not logged', function () {
    $owner = User::factory()->create();

    $task = [
        'id' => 1,
        'activity' => 'Pemeriksaan Barang',
        'description' => '<p>Awal</p>',
        'user_id' => $owner->id,
        'status' => 1,
        'date' => '2026-06-22 11:55:00',
        'start_date' => '2026-06-22 10:00:00',
        'end_date' => '2026-06-22 15:00:00',
        'team_user' => [],
        'comments' => [],
    ];

    Http::fake([
        '*log-activity*' => Http::response(['data' => []]),
        '*activity?id=*' => Http::response(['data' => $task]),
        '*' => Http::response(['status' => 200, 'success' => true, 'data' => []]),
    ]);

    Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->call('startEditing')
        ->set('editDescription', '<p>Diubah</p>')
        ->call('updateTask')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/global/dar/update/1')) {
            return false;
        }

        return $request['date'] === '2026-06-22 11:55:00'
            && $request['start_date'] === '2026-06-22 10:00:00'
            && $request['end_date'] === '2026-06-22 15:00:00';
    });
});
