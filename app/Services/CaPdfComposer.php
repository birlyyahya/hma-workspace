<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Menyusun PDF laporan Cash Advance (rekap transaksi CA PL) sebagai file PDF
 * asli via DomPDF — bukan sekadar halaman HTML yang di-print browser.
 *
 * Fase 1: rekap tabel (halaman utama).
 * Fase 2: lampiran bukti tiap transaksi yang punya bukti, satu bukti satu
 * halaman baru, diberi label nomor sesuai baris di tabel rekap (mis. "A.2"
 * untuk baris penerimaan ke-2, "B.3" untuk baris pengeluaran ke-3) dan judul
 * dari deskripsi transaksi. Lampiran gambar dirender ulang lewat DomPDF
 * (satu halaman per gambar); lampiran PDF digabung apa adanya lewat FPDI
 * (mempertahankan semua halamannya) dengan label ditumpuk di halaman
 * pertamanya. Mengikuti pola SpdPdfComposer.
 */
class CaPdfComposer
{
    public function __construct(private readonly RemoteImageFetcher $images) {}

    /**
     * @param  array<string, mixed>  $balance
     * @param  array<int, array<string, mixed>>  $penerimaan
     * @param  array<int, array<string, mixed>>  $pengeluaran
     */
    public function renderCaPl(array $balance, array $penerimaan, array $pengeluaran, string $projectName, string $preparedBy): string
    {
        $base = Pdf::loadView('pdf.ca-laporan-capl', [
            'balance' => $balance,
            'penerimaan' => $penerimaan,
            'pengeluaran' => $pengeluaran,
            'projectName' => $projectName,
            'preparedBy' => $preparedBy,
        ])->setPaper('a4', 'landscape')->output();

        $attachments = $this->collectAttachments($penerimaan, $pengeluaran);

        if (empty($attachments)) {
            return $base;
        }

        try {
            return $this->appendAttachments($base, $attachments);
        } catch (\Throwable $e) {
            Log::warning('CaPdfComposer gagal melampirkan bukti; kembalikan PDF rekap saja', [
                'message' => $e->getMessage(),
            ]);

            return $base;
        }
    }

    /**
     * Kumpulkan lampiran bukti dalam urutan & nomor yang sama seperti baris
     * di tabel rekap (baris "1" opening tidak punya bukti, jadi penerimaan
     * mulai dari nomor 2).
     *
     * @param  array<int, array<string, mixed>>  $penerimaan
     * @param  array<int, array<string, mixed>>  $pengeluaran
     * @return array<int, array{label: string, title: string, url: string}>
     */
    private function collectAttachments(array $penerimaan, array $pengeluaran): array
    {
        $items = [];

        foreach ($penerimaan as $i => $trx) {
            if (! empty($trx['bukti'])) {
                $items[] = [
                    'label' => 'A.'.($i + 2),
                    'title' => $trx['deskripsi'] ?? 'Bukti Transaksi',
                    'url' => $this->buktiUrl($trx['bukti']),
                ];
            }
        }

        foreach ($pengeluaran as $i => $trx) {
            if (! empty($trx['bukti'])) {
                $items[] = [
                    'label' => 'B.'.($i + 1),
                    'title' => $trx['deskripsi'] ?? 'Bukti Transaksi',
                    'url' => $this->buktiUrl($trx['bukti']),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<int, array{label: string, title: string, url: string}>  $attachments
     */
    private function appendAttachments(string $basePdf, array $attachments): string
    {
        $fpdi = new Fpdi;

        $this->importAllPages($fpdi, $basePdf);

        foreach ($attachments as $item) {
            if ($this->isPdfUrl($item['url'])) {
                $bytes = $this->images->toRawBytes($item['url']);

                if ($bytes === null) {
                    continue;
                }

                $this->importAllPages($fpdi, $bytes, fn () => $this->drawLabel($fpdi, $item));

                continue;
            }

            $image = $this->images->toImageData($item['url']);

            if ($image === null) {
                continue;
            }

            $cover = Pdf::loadView('pdf.ca-bukti-cover', [
                'label' => $item['label'],
                'title' => $item['title'],
                'image' => $image['data'],
            ])->setPaper('a4', 'landscape')->output();

            $this->importAllPages($fpdi, $cover);
        }

        return $fpdi->Output('', 'S');
    }

    /**
     * Import semua halaman satu dokumen PDF ke $fpdi, mempertahankan ukuran &
     * orientasi asli tiap halaman. $onFirstPage (opsional) dipanggil setelah
     * halaman pertama di-template-kan, untuk menumpuk label di atasnya.
     */
    private function importAllPages(Fpdi $fpdi, string $document, ?callable $onFirstPage = null): void
    {
        $pageCount = $fpdi->setSourceFile(StreamReader::createByString($document));

        for ($page = 1; $page <= $pageCount; $page++) {
            $templateId = $fpdi->importPage($page);
            $size = $fpdi->getTemplateSize($templateId);

            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($templateId);

            if ($page === 1 && $onFirstPage !== null) {
                $onFirstPage();
            }
        }
    }

    /**
     * Tumpuk label "No. X — deskripsi" di pojok kiri atas halaman pertama
     * lampiran PDF eksternal (bar putih di belakang teks agar tetap terbaca
     * di atas konten apa pun).
     *
     * @param  array{label: string, title: string, url: string}  $item
     */
    private function drawLabel(Fpdi $fpdi, array $item): void
    {
        $width = $fpdi->GetPageWidth();
        $margin = 4;
        $usableWidth = $width - $margin * 2;
        $lineHeight = 5;

        // FPDF (dasar FPDI) tidak mendukung UTF-8 pada font bawaan (Helvetica) —
        // konversi ke Latin-1 dengan transliterasi agar karakter non-ASCII pada
        // deskripsi tidak tampil rusak (mojibake). Deskripsi tidak dipotong —
        // pakai MultiCell yang wrap ke beberapa baris, tinggi bar putihnya
        // dihitung dari estimasi jumlah baris supaya teks tidak tertutup.
        $text = 'No. '.$item['label'].' - '.$item['title'];
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        $fpdi->SetFont('Helvetica', 'B', 10);
        $lines = max(1, (int) ceil($fpdi->GetStringWidth($text) / $usableWidth));
        $barHeight = $lines * $lineHeight + 4;

        $fpdi->SetFillColor(255, 255, 255);
        $fpdi->Rect(0, 0, $width, $barHeight, 'F');
        $fpdi->SetTextColor(17, 17, 17);
        $fpdi->SetXY($margin, 2);
        $fpdi->MultiCell($usableWidth, $lineHeight, $text, 0, 'L');
    }

    private function isPdfUrl(string $url): bool
    {
        return strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * URL publik MinIO untuk path bukti dari API CA (bucket: cashadvance).
     */
    private function buktiUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return Storage::disk('s3_cashadvance')->temporaryUrl(
            $path,
            now()->addMinutes(60),
            ['ResponseContentDisposition' => 'inline; filename="'.basename($path).'"'],
        );
    }
}
