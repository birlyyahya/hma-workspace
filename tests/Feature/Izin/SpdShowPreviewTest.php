<?php

use App\Models\User;
use App\Services\IzinCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
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
        'date' => '<p>10 Juli 2026 s/d 12 Juli 2026</p>',
        'created_at' => '2026-07-06',
        'is_submitted' => true,
        'is_approved' => true,
        'attachment_url' => null,
    ];
}

test('renders an iframe pointing to the PDF stream route', function () {
    $user = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($user) {
        $mock->shouldReceive('spdList')->andReturn(['data' => [fakeSpdRow($user)]]);
    });

    Volt::actingAs($user)->test('izin.spd-show', ['id' => 1])
        ->assertSee('Preview SPD')
        ->assertSee(route('izin.spd-pdf', 1), false);
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
        ->assertSee('SPD tidak ditemukan');
});

test('the PDF route streams an inline PDF binary', function () {
    $user = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($user) {
        $mock->shouldReceive('spdList')->andReturn(['data' => [fakeSpdRow($user)]]);
    });

    $response = actingAs($user)->get(route('izin.spd-pdf', 1));

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('the PDF route returns 404 for a missing SPD', function () {
    $user = User::factory()->create();

    mock(IzinCache::class, function ($mock) {
        $mock->shouldReceive('spdList')->andReturn(['data' => []]);
    });

    actingAs($user)->get(route('izin.spd-pdf', 999))->assertNotFound();
});
