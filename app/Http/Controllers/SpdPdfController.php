<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IzinCache;
use App\Services\SpdPdfComposer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Stream PDF SPD (2 halaman utama + lampiran) secara inline untuk iframe preview
 * di spd-show. Dipakai sebagai URL langsung (bukan data URI base64) karena PDF
 * dengan lampiran gambar bisa berukuran MB — melebihi batas data-URL browser.
 */
class SpdPdfController extends Controller
{
    public function __invoke(int $id, IzinCache $izinCache, SpdPdfComposer $composer): Response
    {
        $response = $izinCache->spdDetail($id);
        $spd = $response;

        abort_if(! $spd, 404);

        // Template menyembunyikan halaman salinan administrasi untuk user tanpa
        // spd.create — varian PDF-nya berbeda, jadi flag ikut menjadi bagian key
        // agar cache milik pembuat tidak bocor ke user biasa (dan sebaliknya).
        $withAdminCopy = (bool) request()->user()?->can('spd.create');

        $cacheKey = 'spd-pdf-'.$id.'-'.($withAdminCopy ? 'admin' : 'plain').'-'.md5((string) json_encode($spd));

        $bytes = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($spd, $composer, $withAdminCopy) {
            return $composer->render($spd, User::find($spd['user_id'] ?? null), $withAdminCopy);
        });

        $filename = 'SPD-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT).'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
