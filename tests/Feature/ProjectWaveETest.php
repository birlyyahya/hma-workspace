<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(fn () => $this->withViewErrors([]));

test('summary-dar widget reads DAR tasks through DarCache', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['success' => true, 'data' => [
            ['id' => 1, 'activity' => 'Tugas A', 'status' => 1, 'start_date' => '2026-06-01', 'end_date' => '2026-06-10'],
        ]], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('widget.dashboard.summary-dar')->assertStatus(200);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/global/dar/list'));
});

test('task-in-progress widget reads DAR tasks through DarCache', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['success' => true, 'data' => [
            ['id' => 2, 'activity' => 'Tugas B', 'status' => 1, 'start_date' => '2026-06-01', 'end_date' => '2026-06-10'],
        ]], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('widget.dashboard.task-in-progress')
        ->assertSee('Tugas B')
        ->assertStatus(200);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/global/dar/list'));
});

test('signature save posts the base64 through IzinWriter', function () {
    Http::fake([
        '*global/user/get-user/*' => Http::response(['success' => true, 'data' => ['signature' => null]], 200),
        '*global/user/update-signature/*' => Http::response(['success' => true], 200),
    ]);
    $this->actingAs(User::factory()->create(['username' => 'budi']));

    Volt::test('settings.signature')
        ->set('signature', UploadedFile::fake()->image('ttd.png'))
        ->call('save');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/global/user/update-signature/budi')
        && str_starts_with((string) $request['base64'], 'data:'));
});
