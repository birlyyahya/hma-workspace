<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Menyusun PDF laporan Cash Advance (rekap transaksi CA PL) sebagai file PDF
 * asli via DomPDF — bukan sekadar halaman HTML yang di-print browser.
 *
 * Fase 1: hanya rekap tabel (halaman utama).
 * Fase 2 (rencana): gabungkan lampiran bukti tiap transaksi sebagai halaman
 * baru per bukti, memakai pola yang sama dengan SpdPdfComposer (FPDI untuk
 * lampiran PDF, DomPDF untuk lampiran gambar).
 */
class CaPdfComposer
{
    /**
     * @param  array<string, mixed>  $balance
     * @param  array<int, array<string, mixed>>  $penerimaan
     * @param  array<int, array<string, mixed>>  $pengeluaran
     */
    public function renderCaPl(array $balance, array $penerimaan, array $pengeluaran, string $projectName, string $preparedBy): string
    {
        return Pdf::loadView('pdf.ca-laporan-capl', [
            'balance' => $balance,
            'penerimaan' => $penerimaan,
            'pengeluaran' => $pengeluaran,
            'projectName' => $projectName,
            'preparedBy' => $preparedBy,
        ])->setPaper('a4', 'landscape')->output();
    }
}
