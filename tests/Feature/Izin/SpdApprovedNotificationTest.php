<?php

use App\Models\User;
use App\Notifications\SpdApproved;
use App\Services\IzinCache;
use App\Services\IzinWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use NotificationChannels\WebPush\WebPushChannel;

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

function fillSpdApprovalForm($component, User $target)
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

test('saving an approved spd notifies the owner via database and web push', function () {
    Mail::fake();
    Notification::fake();

    $target = User::factory()->create();

    mock(IzinWriter::class, function ($mock) use ($target) {
        $mock->shouldReceive('saveSpd')
            ->once()
            ->andReturn([
                'ok' => true,
                'body' => ['success' => true, 'data' => [
                    'id' => 9,
                    'user_id' => $target->id,
                    'task' => '<ul><li>Survei instalasi</li></ul>',
                    'destination' => 'Bandung',
                ]],
                'status' => 200,
                'error' => null,
            ]);
    });

    fillSpdApprovalForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->set('isSubmitted', true)
        ->set('isApproved', true)
        ->call('saveSpd')
        ->assertHasNoErrors();

    Notification::assertSentTo($target, SpdApproved::class, function (SpdApproved $notification, array $channels) {
        return $notification->spdId === 9 && in_array(WebPushChannel::class, $channels, true);
    });
});

test('saving an unapproved spd sends no approval notification', function () {
    Mail::fake();
    Notification::fake();

    $target = User::factory()->create();

    mock(IzinWriter::class, function ($mock) use ($target) {
        $mock->shouldReceive('saveSpd')
            ->once()
            ->andReturn([
                'ok' => true,
                'body' => ['success' => true, 'data' => ['id' => 9, 'user_id' => $target->id]],
                'status' => 200,
                'error' => null,
            ]);
    });

    fillSpdApprovalForm(Volt::actingAs(User::factory()->create())->test('izin.spd-list'), $target)
        ->call('saveSpd')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

test('the spd approved web push carries the preview url and clean body text', function () {
    $notification = new SpdApproved(
        spdId: 9,
        task: '<ul><li>Survei instalasi</li></ul>',
        destination: 'Bandung',
    );

    expect($notification->via(new stdClass))->toContain('database', WebPushChannel::class);

    $message = $notification->toWebPush(new stdClass, $notification)->toArray();

    expect($message['title'])->toBe('SPD disetujui')
        ->and($message['body'])->not->toContain('<')
        ->and($message['data']['url'])->toBe(route('izin.spd-preview', ['id' => 9]));
});
