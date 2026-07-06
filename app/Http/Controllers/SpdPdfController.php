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
        $response = $izinCache->spdList(['per_page' => 1000]);
        $spd = collect($response['data'] ?? [])->firstWhere('id', $id);

        abort_if(! $spd, 404);

        $cacheKey = 'spd-pdf-'.$id.'-'.md5((string) json_encode($spd));

        $bytes = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($spd, $composer) {
            return $composer->render($spd, User::find($spd['user_id'] ?? null));
        });

        $filename = 'SPD-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT).'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
