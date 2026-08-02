<?php

namespace App\Http\Controllers;

use App\Services\CaCache;
use App\Services\CaPdfComposer;
use App\Services\ProjectCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Stream PDF rekap dompet kegiatan secara inline untuk iframe preview di
 * laporan-kegiatan. Sama persis polanya dengan CaLaporanPlPdfController.
 */
class CaLaporanKegiatanPdfController extends Controller
{
    /**
     * Naikkan tiap kali logika render CaPdfComposer berubah secara berarti —
     * otomatis membuat cache lama usang tanpa perlu Cache::forget manual.
     */
    private const CACHE_VERSION = 2;

    public function __invoke(
        string $kodeCa,
        Request $request,
        CaCache $caCache,
        ProjectCache $projectCache,
        CaPdfComposer $composer
    ): Response {
        $wallet = $caCache->walletKegiatanByKode($kodeCa, ['tr_ca_transaction' => 'true'])['data'] ?? [];

        abort_if(! $wallet, 404);

        $transaksi = collect($caCache->transaksiWalletKegiatan($kodeCa)['data'] ?? []);
        $penerimaan = $transaksi->where('jenis', 'penerimaan')->values()->all();
        $pengeluaran = $transaksi->where('jenis', 'pengeluaran')->values()->all();

        $projects = $projectCache->involvedProjects($request->user()->id);
        $projectId = $request->integer('project_id') ?: ($projects[0]['id'] ?? null);
        $projectName = collect($projects)->firstWhere('id', $projectId)['name']
            ?? ($wallet['judul_kegiatan'] ?? '-');

        // Token "v" dari tombol Generate Ulang ikut jadi bagian key: tiap klik
        // memakai nilai baru yang belum pernah ada di cache, jadi dijamin
        // render ulang fresh, bukan sekadar reload iframe di browser.
        $regenerateToken = $request->integer('v');

        $cacheKey = sprintf(
            'ca-pdf-kegiatan-v%d-%s-%s-%d-%s',
            self::CACHE_VERSION,
            $kodeCa,
            $projectId,
            $regenerateToken,
            md5((string) json_encode([$wallet, $penerimaan, $pengeluaran])),
        );

        $bytes = Cache::remember($cacheKey, now()->addMinutes(15), fn (): string => $composer->renderCaKegiatan(
            $wallet,
            $penerimaan,
            $pengeluaran,
            $projectName,
            $request->user()->name,
        ));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Laporan-CA-Kegiatan-'.$kodeCa.'.pdf"',
        ]);
    }
}
