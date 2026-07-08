<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IzinCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();
});

function spdPreviewCreatorUser(): User
{
    $role = Role::factory()->create();

    $permission = Permission::query()->firstOrCreate(
        ['name' => 'spd.create'],
        ['module' => 'spd', 'action' => 'create', 'label' => 'Create SPD'],
    );

    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id]);
}

function spdPreviewCountPages(string $bytes): int
{
    return (new Fpdi)->setSourceFile(StreamReader::createByString($bytes));
}

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

test('admin copy is cached per permission and never leaks to users without spd.create', function () {
    $target = User::factory()->create();

    mock(IzinCache::class, function ($mock) use ($target) {
        $mock->shouldReceive('spdList')->andReturn(['data' => [fakeSpdRow($target)]]);
    });

    // Pembuat membuka lebih dulu (memanaskan cache dengan varian 2 halaman)...
    $creatorPdf = actingAs(spdPreviewCreatorUser())
        ->get(route('izin.spd-pdf', 1))
        ->assertSuccessful()
        ->getContent();

    // ...user biasa setelahnya tetap menerima varian 1 halaman, bukan cache pembuat.
    $plainPdf = actingAs(User::factory()->create())
        ->get(route('izin.spd-pdf', 1))
        ->assertSuccessful()
        ->getContent();

    expect(spdPreviewCountPages($creatorPdf))->toBe(2)
        ->and(spdPreviewCountPages($plainPdf))->toBe(1);
});
