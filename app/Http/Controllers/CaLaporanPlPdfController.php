<?php

namespace App\Http\Controllers;

use App\Services\CaCache;
use App\Services\CaPdfComposer;
use App\Services\ProjectCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Stream PDF rekap CA PL secara inline untuk iframe preview di laporan-capl,
 * mengikuti pola SpdPdfController: URL langsung (bukan data URI base64) agar
 * bisa membesar wajar saat lampiran bukti ditambahkan di Fase 2.
 */
class CaLaporanPlPdfController extends Controller
{
    public function __invoke(
        string $kodeCa,
        Request $request,
        CaCache $caCache,
        ProjectCache $projectCache,
        CaPdfComposer $composer
    ): Response {
        $wallet = $caCache->walletPlByKode($kodeCa)['data'] ?? [];

        abort_if(! $wallet, 404);

        $transaksi = collect($caCache->transaksiCaPlActive($kodeCa)['data'] ?? []);
        $penerimaan = $transaksi->where('jenis', 'pemasukan')->values()->all();
        $pengeluaran = $transaksi->where('jenis', 'pengeluaran')->values()->all();

        $projects = $projectCache->involvedProjects($request->user()->id);
        $projectId = $request->integer('project_id') ?: ($projects[0]['id'] ?? null);
        $projectName = collect($projects)->firstWhere('id', $projectId)['name']
            ?? ($wallet['judul_kegiatan'] ?? '-');

        $cacheKey = 'ca-pdf-capl-'.$kodeCa.'-'.$projectId.'-'.md5((string) json_encode([$wallet, $penerimaan, $pengeluaran]));

        $bytes = Cache::remember($cacheKey, now()->addMinutes(15), fn (): string => $composer->renderCaPl(
            $wallet,
            $penerimaan,
            $pengeluaran,
            $projectName,
            $request->user()->name,
        ));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Laporan-CA-PL-'.$kodeCa.'.pdf"',
        ]);
    }
}
