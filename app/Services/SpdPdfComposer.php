<?php

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Menyusun PDF SPD final: 2 halaman utama (SPD + salinan administrasi) yang
 * dirender DomPDF, lalu menggabungkan lampiran:
 * - Lampiran gambar (jpg/png/…) di-embed langsung sebagai halaman oleh DomPDF.
 * - Lampiran PDF di-merge di belakang 2 halaman utama memakai FPDI.
 *
 * Bila lampiran PDF gagal di-parse FPDI (mis. versi PDF tidak didukung), PDF
 * utama tetap dikembalikan tanpa menggagalkan proses (best-effort).
 */
class SpdPdfComposer
{
    public function __construct(private readonly RemoteImageFetcher $images) {}

    /**
     * @param  array<string, mixed>  $spd
     * @param  bool  $withAdminCopy  Sertakan halaman salinan administrasi (full TTD) —
     *                               hanya untuk viewer ber-permission spd.create.
     */
    public function render(array $spd, ?User $user, bool $withAdminCopy = false): string
    {
        $attachmentUrl = $spd['attachment_url'] ?? null;
        $attachmentIsPdf = $this->attachmentIsPdf($attachmentUrl);

        $base = Pdf::loadView('pdf.spd-pdf', [
            'spd' => $spd,
            'user' => $user,
            'role' => $user?->role,
            'withAdminCopy' => $withAdminCopy,
            'attachmentImage' => $attachmentIsPdf ? null : $this->images->toImageData($attachmentUrl),
        ])->setPaper('A4', 'portrait')->output();

        if (! $attachmentIsPdf) {
            return $base;
        }

        $attachmentPdf = $this->images->toRawBytes($attachmentUrl);

        if ($attachmentPdf === null) {
            return $base;
        }

        try {
            return $this->merge([$base, $attachmentPdf]);
        } catch (\Throwable $e) {
            Log::warning('SpdPdfComposer merge lampiran PDF gagal; kembalikan PDF utama', [
                'url' => $attachmentUrl,
                'message' => $e->getMessage(),
            ]);

            return $base;
        }
    }

    /**
     * Gabungkan beberapa dokumen PDF (dalam bentuk bytes) menjadi satu, halaman
     * per halaman, mempertahankan ukuran & orientasi tiap halaman sumber.
     *
     * @param  array<int, string>  $documents
     */
    public function merge(array $documents): string
    {
        $fpdi = new Fpdi;

        foreach ($documents as $document) {
            $pageCount = $fpdi->setSourceFile(StreamReader::createByString($document));

            for ($page = 1; $page <= $pageCount; $page++) {
                $templateId = $fpdi->importPage($page);
                $size = $fpdi->getTemplateSize($templateId);

                $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $fpdi->useTemplate($templateId);
            }
        }

        return $fpdi->Output('', 'S');
    }

    private function attachmentIsPdf(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        return strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) === 'pdf';
    }
}
