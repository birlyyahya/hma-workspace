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
    /**
     * Naikkan tiap kali logika render CaPdfComposer berubah secara berarti
     * (mis. Fase 2 menambah lampiran bukti) — otomatis membuat cache lama
     * usang tanpa perlu Cache::forget manual saat deploy.
     */
    private const CACHE_VERSION = 2;

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

        // Token "v" dari tombol Generate Ulang ikut jadi bagian key: tiap klik
        // memakai nilai baru yang belum pernah ada di cache, jadi dijamin
        // render ulang fresh, bukan sekadar reload iframe di browser.
        $regenerateToken = $request->integer('v');

        $cacheKey = sprintf(
            'ca-pdf-capl-v%d-%s-%s-%d-%s',
            self::CACHE_VERSION,
            $kodeCa,
            $projectId,
            $regenerateToken,
            md5((string) json_encode([$wallet, $penerimaan, $pengeluaran])),
        );

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
