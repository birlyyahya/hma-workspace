<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::flush();
});

test('team_user user_ids are resolved to workspace user names', function () {
    $creator = User::factory()->create(['name' => 'Ignasius']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Http::fake([
        '*' => Http::response(['data' => [[
            'id' => 1,
            'activity' => 'Site visit',
            'start_date' => now()->toDateTimeString(),
            'user_id' => $creator->id,
            'team_user' => [
                ['user_id' => $alice->id],
                ['user_id' => $bob->id],
                ['user_id' => 999999], // unknown user => name null, falls back to id in view
            ],
        ]]]),
    ]);

    $this->actingAs($creator);

    $events = Volt::test('widget.dashboard.event-calendar')->get('eventMap');

    $event = collect($events)->flatten(1)->firstWhere('id', 1);

    expect($event['user_name'])->toBe('Ignasius');
    expect($event['team_user'])->toBe([
        ['user_id' => $alice->id, 'name' => 'Alice'],
        ['user_id' => $bob->id, 'name' => 'Bob'],
        ['user_id' => 999999, 'name' => null],
    ]);
});

test('creator name is rendered on the event card', function () {
    $creator = User::factory()->create(['name' => 'Ignasius Hargilianto']);

    Http::fake([
        '*' => Http::response(['data' => [[
            'id' => 1,
            'activity' => 'Site visit',
            'start_date' => now()->toDateTimeString(),
            'user_id' => $creator->id,
            'team_user' => [],
        ]]]),
    ]);

    $this->actingAs($creator);

    Volt::test('widget.dashboard.event-calendar')
        ->assertSee('Pembuat: Ignasius Hargilianto');
});
