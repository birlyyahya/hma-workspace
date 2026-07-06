<?php

use Barryvdh\DomPDF\Facade\Pdf;

function renderSpdPdfHtml(array $overrides = []): string
{
    $spd = array_merge([
        'number' => 7,
        'task' => '<ul><li>Survei instalasi</li><li>Uji jaringan</li></ul>',
        'department' => '<p>IT RnD</p>',
        'destination' => '<ul><li>Bandung</li></ul>',
        'address' => '<p>Jl. Merdeka No. 1</p>',
        'start_date' => '<ul><li>10 Juli 2026 s/d 12 Juli 2026</li></ul>',
        'end_date' => '',
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

test('renders two main SPD pages with the administrasi stamp', function () {
    $html = renderSpdPdfHtml();

    expect(substr_count($html, 'SURAT PERJALANAN DINAS'))->toBe(2)
        ->and(substr_count($html, 'class="stamp"'))->toBe(1);
});

test('renders the rich-text fields as HTML lists', function () {
    $html = renderSpdPdfHtml();

    expect($html)->toContain('<li>Survei instalasi</li>')
        ->and($html)->toContain('<li>Uji jaringan</li>')
        ->and($html)->toContain('10 Juli 2026 s/d 12 Juli 2026');
});

test('administrasi page always shows both signatures even when not submitted', function () {
    $html = renderSpdPdfHtml(['is_submitted' => false, 'is_approved' => false]);

    // Page 1 (not submitted) has no signature image; the administrasi copy adds both.
    expect(substr_count($html, 'alt="TTD Andre"'))->toBe(1)
        ->and(substr_count($html, 'alt="TTD Irwan"'))->toBe(1);
});

test('checklist page shows signatures when submitted and approved', function () {
    $html = renderSpdPdfHtml(['is_submitted' => true, 'is_approved' => true]);

    // Both pages now show both signatures.
    expect(substr_count($html, 'alt="TTD Andre"'))->toBe(2)
        ->and(substr_count($html, 'alt="TTD Irwan"'))->toBe(2);
});

test('produces a valid PDF binary through DomPDF', function () {
    $output = Pdf::loadView('pdf.spd-pdf', [
        'spd' => [
            'number' => 1,
            'task' => '<ul><li>Tugas</li></ul>',
            'department' => 'IT',
            'destination' => 'Jakarta',
            'address' => 'Alamat',
            'start_date' => '<p>Hari ini</p>',
            'end_date' => '',
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
