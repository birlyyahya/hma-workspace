<?php

test('izin PDF renders the selected reason as a checked checklist option', function () {
    $html = view('pdf.izin-pdf', [
        'izin' => [
            'reason' => 'Dinas luar kota',
            'description' => 'Keperluan pekerjaan',
            'start_date' => '2026-10-23',
            'end_date' => '2026-10-23',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'users' => [
                'name' => 'Pemohon',
                'divisi' => 'IT',
            ],
        ],
    ])->render();

    expect($html)
        ->toContain('Mengajukan izin :')
        ->toContain('Dinas luar kota')
        ->toContain('Datang terlambat')
        ->toContain('Sakit');

    expect(substr_count($html, '✓'))->toBe(1);
});
