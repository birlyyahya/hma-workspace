<?php

use App\Models\User;
use App\Services\SpdPdfComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

uses(RefreshDatabase::class);

function makePdfBytes(int $pages): string
{
    $pdf = new Fpdi;

    for ($i = 1; $i <= $pages; $i++) {
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Lampiran halaman '.$i);
    }

    return $pdf->Output('', 'S');
}

function countPdfPages(string $bytes): int
{
    return (new Fpdi)->setSourceFile(StreamReader::createByString($bytes));
}

function spdFixture(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
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
    ], $overrides);
}

test('admin copy variant renders two main pages when there is no attachment', function () {
    $user = User::factory()->create();

    $pdf = app(SpdPdfComposer::class)->render(spdFixture(), $user, withAdminCopy: true);

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(countPdfPages($pdf))->toBe(2);
});

test('plain variant renders a single main page (no admin copy)', function () {
    $user = User::factory()->create();

    $pdf = app(SpdPdfComposer::class)->render(spdFixture(), $user);

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(countPdfPages($pdf))->toBe(1);
});

test('merges a PDF attachment behind the two main pages of the admin variant', function () {
    Http::fake([
        'darbe.test/*' => Http::response(makePdfBytes(2), 200, ['Content-Type' => 'application/pdf']),
    ]);

    $user = User::factory()->create();

    $pdf = app(SpdPdfComposer::class)->render(
        spdFixture(['attachment_url' => 'https://darbe.test/files/lampiran.pdf']),
        $user,
        withAdminCopy: true,
    );

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(countPdfPages($pdf))->toBe(4);
});

test('embeds an image attachment as an additional page of the admin variant', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

    Http::fake([
        'darbe.test/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $user = User::factory()->create();

    $pdf = app(SpdPdfComposer::class)->render(
        spdFixture(['attachment_url' => 'https://darbe.test/files/lampiran.png']),
        $user,
        withAdminCopy: true,
    );

    expect(countPdfPages($pdf))->toBe(3);
});

test('plain variant keeps checklist page plus attachment only', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

    Http::fake([
        'darbe.test/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $user = User::factory()->create();

    $pdf = app(SpdPdfComposer::class)->render(
        spdFixture(['attachment_url' => 'https://darbe.test/files/lampiran.png']),
        $user,
    );

    expect(countPdfPages($pdf))->toBe(2);
});

test('falls back to the main PDF when the PDF attachment cannot be parsed', function () {
    Http::fake([
        'darbe.test/*' => Http::response('this-is-not-a-pdf', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $user = User::factory()->create();

    $pdf = app(SpdPdfComposer::class)->render(
        spdFixture(['attachment_url' => 'https://darbe.test/files/broken.pdf']),
        $user,
        withAdminCopy: true,
    );

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(countPdfPages($pdf))->toBe(2);
});

test('merge concatenates page counts of all documents', function () {
    $merged = app(SpdPdfComposer::class)->merge([makePdfBytes(2), makePdfBytes(3)]);

    expect(countPdfPages($merged))->toBe(5);
});
