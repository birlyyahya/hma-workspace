<?php

use Barryvdh\DomPDF\Facade\Pdf;

function renderSpdPdfHtml(array $overrides = [], bool $withAdminCopy = false): string
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
        'withAdminCopy' => $withAdminCopy,
        'attachmentImage' => null,
    ])->render();
}

test('admin variant renders two main pages with a floating Lampiran stamp', function () {
    $html = renderSpdPdfHtml(withAdminCopy: true);

    expect(substr_count($html, 'SURAT PERJALANAN DINAS'))->toBe(2)
        ->and(substr_count($html, 'class="stamp"'))->toBe(1)
        ->and($html)->toContain('<div class="stamp">Lampiran</div>');
});

test('plain variant renders only the checklist page, no admin copy or stamp', function () {
    $html = renderSpdPdfHtml();

    expect(substr_count($html, 'SURAT PERJALANAN DINAS'))->toBe(1)
        ->and($html)->not->toContain('class="stamp"');
});

test('renders the rich-text fields as HTML lists', function () {
    $html = renderSpdPdfHtml();

    expect($html)->toContain('<li>Survei instalasi</li>')
        ->and($html)->toContain('<li>Uji jaringan</li>')
        ->and($html)->toContain('10 Juli 2026 s/d 12 Juli 2026');
});

test('admin copy always shows both signatures even when not submitted', function () {
    $html = renderSpdPdfHtml(['is_submitted' => false, 'is_approved' => false], withAdminCopy: true);

    // Page 1 (not submitted) has no signature image; the administrasi copy adds both.
    expect(substr_count($html, 'alt="TTD Andre"'))->toBe(1)
        ->and(substr_count($html, 'alt="TTD Irwan"'))->toBe(1);
});

test('plain variant shows no signatures when the SPD is not submitted', function () {
    $html = renderSpdPdfHtml(['is_submitted' => false, 'is_approved' => false]);

    expect($html)->not->toContain('alt="TTD Andre"')
        ->and($html)->not->toContain('alt="TTD Irwan"');
});

test('checklist page shows signatures when submitted and approved', function () {
    $html = renderSpdPdfHtml(['is_submitted' => true, 'is_approved' => true]);

    // Only the checklist page (plain variant has no admin copy).
    expect(substr_count($html, 'alt="TTD Andre"'))->toBe(1)
        ->and(substr_count($html, 'alt="TTD Irwan"'))->toBe(1);
});

test('produces a valid PDF binary through DomPDF', function () {
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
        'withAdminCopy' => true,
        'attachmentImage' => null,
    ])->setPaper('A4', 'portrait')->output();

    expect(substr($output, 0, 4))->toBe('%PDF');
});
