<?php

namespace App\Services;

use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Log;

/**
 * Ambil aset gambar remote (tanda tangan, lampiran SPD) lalu encode ke data URI base64
 * untuk disisipkan ke PDF (DomPDF). Ini bukan call JSON API domain — tidak di-cache
 * (binary besar, jarang dipakai ulang) — hanya memusatkan akses Http:: keluar dari view
 * supaya timeout/retry konsisten lewat trait MakesExternalRequests.
 */
class RemoteImageFetcher
{
    use MakesExternalRequests;

    /**
     * Ambil gambar apa pun dan kembalikan sebagai data URI base64, atau null bila gagal.
     */
    public function toDataUri(?string $url, int $timeout = 5): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $content = $this->externalRead(timeout: $timeout)->get($url)->body();

            if (! $content) {
                return null;
            }

            $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($content);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ambil lampiran gambar (validasi ekstensi dulu) dan kembalikan data URI + mime,
     * atau null bila URL bukan gambar / fetch gagal.
     *
     * @return array{data: string, mime: string}|null
     */
    public function toImageData(?string $url, int $timeout = 15): ?array
    {
        if (! $url) {
            return null;
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        try {
            $response = $this->externalRead(timeout: $timeout)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $body) ?: 'image/png';

            return [
                'data' => 'data:'.$mime.';base64,'.base64_encode($body),
                'mime' => $mime,
            ];
        } catch (\Throwable $e) {
            Log::warning('RemoteImageFetcher fetch failed', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }
    }
}
