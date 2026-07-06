<?php

use App\Models\User;
use App\Services\IzinCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();
});

function fakeSpdRow(User $user): array
{
    return [
        'id' => 1,
        'user_id' => $user->id,
        'number' => 3,
        'task' => '<ul><li>Survei</li></ul>',
        'department' => '<p>IT RnD</p>',
        'destination' => '<p>Bandung</p>',
        'address' => '<p>Jl. Merdeka</p>',
        'start_date' => '<p>10 Juli 2026 s/d 12 Juli 2026</p>',
        'end_date' => '',
        'created_at' => '2026-07-06',
        'is_submitted' => true,
        'is_approved' => true,
        'attachment_url' => null,
    ];
}

test('auto-generates a base64 PDF preview on mount', function () {
    $user = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($user) {
        $mock->shouldReceive('spdList')->andReturn(['data' => [fakeSpdRow($user)]]);
    });

    Volt::actingAs($user)->test('izin.spd-show', ['id' => 1])
        ->assertSet('pdfPreview', fn ($value) => is_string($value) && str_starts_with($value, 'data:application/pdf;base64,'))
        ->assertSee('Preview SPD')
        ->assertSee('iframe', false);
});

test('does not render print or download buttons in the header', function () {
    $user = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($user) {
        $mock->shouldReceive('spdList')->andReturn(['data' => [fakeSpdRow($user)]]);
    });

    Volt::actingAs($user)->test('izin.spd-show', ['id' => 1])
        ->assertDontSee('Download PDF')
        ->assertDontSee('window.print');
});

test('shows a not-found state when the SPD is missing', function () {
    $user = User::factory()->create();

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('spdList')->andReturn(['data' => []]);
    });

    Volt::actingAs($user)->test('izin.spd-show', ['id' => 999])
        ->assertSet('pdfPreview', null)
        ->assertSee('SPD tidak ditemukan');
});
