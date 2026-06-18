<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 09:00:00');
    Http::fake(['*' => Http::response(['data' => []], 200)]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('event calendar renders the active month and starts on today', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('widget.dashboard.event-calendar')
        ->assertStatus(200)
        ->assertSet('selectedDate', '2026-06-15')
        ->assertSee('Juni')
        ->assertSee('2026');
});

test('navigating months updates the displayed period', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('widget.dashboard.event-calendar')
        ->call('nextMonth')
        ->assertSee('Juli')
        ->call('prevMonth')
        ->call('prevMonth')
        ->assertSee('Mei');
});
