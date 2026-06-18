<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

test('the custom 403 page renders inside the sidebar layout for authenticated users', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    $html = view('errors.403', [
        'exception' => new AccessDeniedHttpException('Pesan khusus akses ditolak'),
    ])->render();

    expect($html)
        ->toContain('Akses Ditolak')
        ->toContain('Error 403')
        ->toContain('Pesan khusus akses ditolak')
        ->toContain('Ke Dashboard')
        ->toContain('Knowledge Hub'); // sidebar present
});

test('the custom 403 page shows the default message when none is given', function () {
    Http::fake();

    $this->actingAs(User::factory()->create());

    $html = view('errors.403', [
        'exception' => new AccessDeniedHttpException(''),
    ])->render();

    expect($html)->toContain('tidak memiliki izin');
});

test('the custom 403 page shows a sign-in action for guests without a sidebar', function () {
    $html = view('errors.403', [
        'exception' => new AccessDeniedHttpException('Silakan masuk'),
    ])->render();

    expect($html)
        ->toContain('Akses Ditolak')
        ->toContain('Silakan masuk')
        ->toContain('Masuk')
        ->not->toContain('Knowledge Hub');
});
