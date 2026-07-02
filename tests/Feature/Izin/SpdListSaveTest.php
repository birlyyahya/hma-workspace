<?php

use App\Jobs\SendWhatsappJob;
use App\Mail\NotificationSpdMail;
use App\Models\User;
use App\Services\IzinCache;
use App\Services\IzinWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(function () {
    Livewire::withoutLazyLoading();

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('spdList')->andReturn([
            'data' => [],
            'total' => 0,
            'current_page' => 1,
            'last_page' => 1,
        ]);
    });
});

function fillSpdForm($component, User $target)
{
    return $component
        ->set('userId', $target->id)
        ->set('number', 1)
        ->set('task', 'Survei instalasi')
        ->set('department', 'IT RnD')
        ->set('destination', 'Bandung')
        ->set('address', 'Jl. Merdeka No. 1')
        ->set('startDate', '2026-07-10')
        ->set('endDate', '2026-07-12');
}

test('creates an spd, resets the form, and does not notify when not approved', function () {
    Mail::fake();
    Queue::fake();

    $target = User::factory()->create();

    mock(IzinWriter::class, function ($mock) use ($target) {
        $mock->shouldReceive('saveSpd')
            ->once()
            ->andReturn([
                'ok' => true,
                'body' => ['success' => true, 'data' => ['user_id' => $target->id]],
                'status' => 200,
                'error' => null,
            ]);
    });

    fillSpdForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->call('saveSpd')
        ->assertHasNoErrors()
        ->assertSet('editingId', null)
        ->assertSet('task', '');

    Mail::assertNothingQueued();
    Queue::assertNothingPushed();
});

test('updating an approved spd succeeds even when the response omits user_id', function () {
    Mail::fake();
    Queue::fake();

    $target = User::factory()->create();

    mock(IzinWriter::class, function ($mock) {
        $mock->shouldReceive('saveSpd')
            ->once()
            ->andReturn([
                'ok' => true,
                'body' => ['success' => true, 'data' => []],
                'status' => 200,
                'error' => null,
            ]);
    });

    fillSpdForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->set('editingId', 5)
        ->set('isSubmitted', true)
        ->set('isApproved', true)
        ->call('saveSpd')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    // Falls back to the selected user for the approval notification.
    Mail::assertQueued(NotificationSpdMail::class);
    Queue::assertPushed(SendWhatsappJob::class);
});

test('keeps the form open and shows no reset when the write fails', function () {
    $target = User::factory()->create();

    mock(IzinWriter::class, function ($mock) {
        $mock->shouldReceive('saveSpd')
            ->once()
            ->andReturn([
                'ok' => false,
                'body' => ['message' => 'Gagal menyimpan SPD.'],
                'status' => 500,
                'error' => null,
            ]);
    });

    fillSpdForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->call('saveSpd')
        ->assertHasNoErrors()
        ->assertSet('task', 'Survei instalasi');
});
