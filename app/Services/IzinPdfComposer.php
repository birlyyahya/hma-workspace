<?php

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class IzinPdfComposer
{
    public function render(array $izin): ?string
    {
        $payload = $this->prepare($izin);

        if ($payload === null) {
            return null;
        }

        $pdf = Pdf::loadView('pdf.izin-pdf', [
            'izin' => $payload,
        ])->setPaper('A4', 'portrait');

        return 'data:application/pdf;base64,'.base64_encode($pdf->output());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function prepare(array $izin): ?array
    {
        $pemohon = User::with('role.department.parent')
            ->where('username', data_get($izin, 'users.username'))
            ->first();

        if (! $pemohon) {
            return null;
        }

        $approvers = $pemohon->izinApprovers();
        $izin['users'] = $pemohon->toArray();
        $izin['users']['signature'] = $this->signatureToDataUri(
            data_get($izin, 'url_sign') ?: $pemohon->getAttribute('signature')
        );

        $izin['approval_signatures'] = $approvers->values()->map(function (User $approver) use ($izin) {
            $isDirector = $approver->hasRole(['direktur', 'director', 'gm']);
            $isManager = $approver->hasRole('manager');
            $fallback = $isDirector || $isManager
                ? data_get($izin, 'superadmins')
                : data_get($izin, 'admins');
            $signature = $approver->getAttribute('signature') ?: $fallback;

            return [
                'title' => $isDirector || $isManager ? 'Disetujui Oleh' : 'Mengetahui Oleh',
                'role' => $isDirector ? 'Direktur' : ($isManager ? 'Manager' : 'Atasan Langsung'),
                'name' => $approver->name,
                'signature_base64' => $this->signatureToDataUri($signature),
            ];
        })->all();

        return $izin;
    }

    private function signatureToDataUri(?string $signature): ?string
    {
        if (! $signature) {
            return null;
        }

        if (str_starts_with($signature, 'data:')) {
            return $signature;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3_izindar');
        $url = filter_var($signature, FILTER_VALIDATE_URL)
            ? $signature
            : $disk->temporaryUrl(
                ltrim(str_replace('storage/izindar/', '', $signature), '/'),
                now()->addMinutes(5),
            );

        return app(RemoteImageFetcher::class)->toDataUri($url);
    }
}
