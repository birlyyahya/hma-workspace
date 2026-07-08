<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function spdPdfCreatorUser(): User
{
    $role = Role::factory()->create();

    $permission = Permission::query()->firstOrCreate(
        ['name' => 'spd.create'],
        ['module' => 'spd', 'action' => 'create', 'label' => 'Create SPD'],
    );

    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id]);
}

function renderSpdPdfHtml(array $overrides = []): string
{
    $spd = array_merge([
        'number' => 7,
        'task' => '<ul><li>Survei instalasi</li><li>Uji jaringan</li></ul>',
        'department' => '<p>IT RnD</p>',
        'destination' => '<ul><li>Bandung</li></ul>',
        'address' => '<p>Jl. Merdeka No. 1</p>',
        'date' => '<ul><li>10 Juli 2026 s/d 12 Juli 2026</li></ul>',
        'created_at' => '2026-07-06',
        'is_submitted' => false,
        'is_approved' => false,
    ], $overrides);

    return view('pdf.spd-pdf', [
        'spd' => $spd,
        'user' => ['name' => 'Budi Santoso'],
        'role' => ['name' => 'Staff IT'],
        'attachmentImage' => null,
    ])->render();
}

test('spd.create user gets two main pages with a floating Lampiran stamp', function () {
    actingAs(spdPdfCreatorUser());

    $html = renderSpdPdfHtml();

    expect(substr_count($html, 'SURAT PERJALANAN DINAS'))->toBe(2)
        ->and(substr_count($html, 'class="stamp"'))->toBe(1)
        ->and($html)->toContain('<div class="stamp">Lampiran</div>');
});

test('user without spd.create gets only the checklist page, no admin copy or stamp', function () {
    actingAs(User::factory()->create());

    $html = renderSpdPdfHtml();

    expect(substr_count($html, 'SURAT PERJALANAN DINAS'))->toBe(1)
        ->and($html)->not->toContain('class="stamp"');
});

test('renders the rich-text fields as HTML lists', function () {
    actingAs(User::factory()->create());

    $html = renderSpdPdfHtml();

    expect($html)->toContain('<li>Survei instalasi</li>')
        ->and($html)->toContain('<li>Uji jaringan</li>')
        ->and($html)->toContain('10 Juli 2026 s/d 12 Juli 2026');
});

test('admin copy always shows both signatures for spd.create even when not submitted', function () {
    actingAs(spdPdfCreatorUser());

    $html = renderSpdPdfHtml(['is_submitted' => false, 'is_approved' => false]);

    // Page 1 (not submitted) has no signature image; the administrasi copy adds both.
    expect(substr_count($html, 'alt="TTD Andre"'))->toBe(1)
        ->and(substr_count($html, 'alt="TTD Irwan"'))->toBe(1);
});

test('user without spd.create sees no signatures when the SPD is not submitted', function () {
    actingAs(User::factory()->create());

    $html = renderSpdPdfHtml(['is_submitted' => false, 'is_approved' => false]);

    expect($html)->not->toContain('alt="TTD Andre"')
        ->and($html)->not->toContain('alt="TTD Irwan"');
});

test('checklist page shows signatures when submitted and approved', function () {
    actingAs(User::factory()->create());

    $html = renderSpdPdfHtml(['is_submitted' => true, 'is_approved' => true]);

    // Only the checklist page (admin copy hidden for non-creators).
    expect(substr_count($html, 'alt="TTD Andre"'))->toBe(1)
        ->and(substr_count($html, 'alt="TTD Irwan"'))->toBe(1);
});

test('produces a valid PDF binary through DomPDF', function () {
    actingAs(User::factory()->create());

    $output = Pdf::loadView('pdf.spd-pdf', [
        'spd' => [
            'number' => 1,
            'task' => '<ul><li>Tugas</li></ul>',
            'department' => 'IT',
            'destination' => 'Jakarta',
            'address' => 'Alamat',
            'date' => '<p>Hari ini</p>',
            'created_at' => '2026-07-06',
            'is_submitted' => true,
            'is_approved' => false,
        ],
        'user' => ['name' => 'Budi'],
        'role' => ['name' => 'Staff'],
        'attachmentImage' => null,
    ])->setPaper('A4', 'portrait')->output();

    expect(substr($output, 0, 4))->toBe('%PDF');
});
