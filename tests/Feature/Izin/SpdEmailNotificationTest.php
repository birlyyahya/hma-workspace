<?php

use App\Mail\NotificationSpdMail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IzinCache;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();
});

function spdEmailCreatorUser(): User
{
    $role = Role::factory()->create();

    $permission = Permission::query()->firstOrCreate(
        ['name' => 'spd.create'],
        ['module' => 'spd', 'action' => 'create', 'label' => 'Create SPD'],
    );

    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id]);
}

function spdEmailRow(User $target, array $overrides = []): array
{
    return array_merge([
        'id' => 9,
        'user_id' => $target->id,
        'number' => 9,
        'task' => '<ul><li>Survei</li></ul>',
        'department' => '<p>IT RnD</p>',
        'destination' => '<p>Bandung</p>',
        'address' => '<p>Jl. Merdeka</p>',
        'date' => '<p>10 Juli 2026 s/d 12 Juli 2026</p>',
        'created_at' => '2026-07-06',
        'is_submitted' => true,
        'is_approved' => true,
        'attachment_url' => null,
    ], $overrides);
}

test('queued notification logs a queued activity', function () {
    Mail::fake();

    $target = User::factory()->create();

    NotificationService::send($target, 'pesan', spdEmailRow($target));

    Mail::assertQueued(NotificationSpdMail::class);

    $activity = Activity::query()->where('log_name', 'izin')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('queued')
        ->and($activity->properties['spd_id'])->toBe(9)
        ->and($activity->properties['email'])->toBe($target->email);
});

test('sendSpdEmailNow renders the full mail (with PDF attachment) and logs sent', function () {
    // Tanpa Mail::fake — mailer `array` merender pesan utuh termasuk lampiran
    // PDF via SpdPdfComposer, sehingga regresi render (mis. variabel hilang
    // di template) tertangkap di sini.
    $target = User::factory()->create();

    NotificationService::sendSpdEmailNow($target, spdEmailRow($target));

    $activity = Activity::query()->where('log_name', 'izin')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('sent')
        ->and($activity->properties['spd_id'])->toBe(9);
});

test('spd-list sendEmail sends the mail for a user with spd.create', function () {
    Mail::fake();

    $creator = spdEmailCreatorUser();
    $target = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($target) {
        $mock->shouldReceive('spdList')->andReturn([
            'data' => [spdEmailRow($target)],
            'total' => 1,
            'current_page' => 1,
            'last_page' => 1,
        ]);
    });

    Volt::actingAs($creator)->test('izin.spd-list')
        ->call('sendEmail', 9)
        ->assertHasNoErrors();

    Mail::assertSent(NotificationSpdMail::class, fn ($mail) => $mail->hasTo($target->email));
});

test('spd-list sendEmail refuses users without spd.create', function () {
    Mail::fake();

    $target = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($target) {
        $mock->shouldReceive('spdList')->andReturn([
            'data' => [spdEmailRow($target)],
            'total' => 1,
            'current_page' => 1,
            'last_page' => 1,
        ]);
    });

    Volt::actingAs(User::factory()->create())->test('izin.spd-list')
        ->call('sendEmail', 9);

    Mail::assertNothingSent();
});

test('spd-show sendEmail sends the mail from the preview header', function () {
    Mail::fake();

    $creator = spdEmailCreatorUser();
    $target = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($target) {
        $mock->shouldReceive('spdList')->andReturn(['data' => [spdEmailRow($target)]]);
    });

    Volt::actingAs($creator)->test('izin.spd-show', ['id' => 9])
        ->call('sendEmail')
        ->assertHasNoErrors();

    Mail::assertSent(NotificationSpdMail::class, fn ($mail) => $mail->hasTo($target->email));
});
