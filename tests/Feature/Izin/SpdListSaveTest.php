<?php

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
        ->set('task', '<ul><li>Survei instalasi</li></ul>')
        ->set('department', 'IT RnD')
        ->set('destination', 'Bandung')
        ->set('address', 'Jl. Merdeka No. 1')
        ->set('masaTugas', '<p>10 Juli 2026 s/d 12 Juli 2026</p>');
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

test('maps the rich-text masa tugas into the date payload', function () {
    $target = User::factory()->create();

    mock(IzinWriter::class, function ($mock) use ($target) {
        $mock->shouldReceive('saveSpd')
            ->once()
            ->withArgs(function ($id, $payload, $file) {
                return $payload['date'] === '<p>10 Juli 2026 s/d 12 Juli 2026</p>'
                    && $payload['task'] === '<ul><li>Survei instalasi</li></ul>';
            })
            ->andReturn([
                'ok' => true,
                'body' => ['success' => true, 'data' => ['user_id' => $target->id]],
                'status' => 200,
                'error' => null,
            ]);
    });

    fillSpdForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->call('saveSpd')
        ->assertHasNoErrors();
});

test('treats an empty rich-text editor value as a required-field error', function () {
    $target = User::factory()->create();

    fillSpdForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->set('task', '<p><br></p>')
        ->call('saveSpd')
        ->assertHasErrors(['task' => 'required']);
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
    // (Job WhatsApp sedang dinonaktifkan di NotificationService.)
    Mail::assertQueued(NotificationSpdMail::class);
});

test('does not crash when the API returns validation errors as an array under message', function () {
    $target = User::factory()->create();

    mock(IzinWriter::class, function ($mock) {
        $mock->shouldReceive('saveSpd')
            ->once()
            ->andReturn([
                'ok' => false,
                'body' => [
                    'success' => false,
                    'message' => [
                        'task' => ['The task must not be greater than 255 characters.'],
                        'end_date' => ['The end date field is required.'],
                    ],
                ],
                'status' => 422,
                'error' => null,
            ]);
    });

    fillSpdForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->call('saveSpd')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);
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
        ->assertSet('task', '<ul><li>Survei instalasi</li></ul>');
});
