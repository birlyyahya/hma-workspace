<?php

use App\Http\Controllers\ProjectFileChunkUploadController;
use App\Http\Controllers\ProjectFileUploadController;
use App\Livewire\Events\EventShow;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect('dashboard');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    // Project
    Route::prefix('projects')->group(function () {
        Route::view('/', 'projects')->name('projects');
        Volt::route('/perusahaan', 'project.perusahaan')->name('perusahaan');
        Volt::route('/create', 'project.project-create')->name('projects.create');
        Volt::route('/edit/{id}', 'project.project-edit')->name('projects.edit');
        Volt::route('/show/{id}', 'project.project-show')->name('projects.show');
        Volt::route('/preview/{id}', 'project.project-preview')->name('projects.preview');
        Volt::route('/gantt-print/{id}', 'project.gantt-print')->name('projects.gantt-print');
    });

    // DAR
    Route::view('dar', 'daily-report')->name('dar');
    Volt::route('dar/tasks/{id}', 'dar.dar-show')->name('dar.dar-show');

    // Izin
    Route::view('izin', 'izin')->name('izin');
    Volt::route('izin/quick', 'izin.quick-izin')->middleware('mobile')->name('izin.quick');
    Volt::route('izin/{id}/detail', 'izin.izin-show')->name('izin.show');
    Volt::route('izin/{id}/pdf', 'izin.izin-show-pdf')->name('izin.pdf');
    Volt::route('izin/laporan-pengajuan', 'izin.laporan-pengajuan-izin')->name('izin.laporan-pengajuan');
    Volt::route('izin/spd/{id}/preview', 'izin.spd-show')->name('izin.spd-preview');
    Route::get('izin/spd/{id}/pdf', \App\Http\Controllers\SpdPdfController::class)->name('izin.spd-pdf');

    // Inventaris
    // Route::view('inventaris', 'inventaris')->name('inventaris');
    Volt::route('inventaris', 'maintenance/comingsoon')->name('inventaris');

    // Cash Advance
    Route::view('cashadvance', 'cashadvance')->name('cashadvance');
    Volt::route('cashadvance/dompet/{kodeCa}', 'cashadvance.dompet-show')->name('cashadvance.dompet-show');
    Volt::route('cashadvance/laporan/ca-pl/{seq?}', 'cashadvance.laporan-capl')->name('cashadvance.laporan.capl');
    Volt::route('cashadvance/laporan/kegiatan/{kodeCa}', 'cashadvance.laporan-kegiatan')->name('cashadvance.laporan.kegiatan');
    // Volt::route('cashadvance', 'maintenance/comingsoon')->name('cashadvance');

    // Pengajuan Barang
    // Route::prefix('pengajuan-barang')->group(function () {
    //     Route::view('/', 'pengajuan-barang')->name('pengajuan-barang');
    //     Volt::route('/create', 'pengajuan-barang.pengajuan-create')->name('pengajuan-barang.create');
    //     Volt::route('/{kode}', 'pengajuan-barang.pengajuan-show')->name('pengajuan-barang.show');
    // });

    // Event
    Route::prefix('event')->group(function () {
        Route::view('/', 'events')->name('events');
        // Volt::route('/', 'maintenance/comingsoon')->name('events');
        Route::get('/{event}', EventShow::class)->name('events.show');
        Route::view('/{event}/scan', 'events-scan')->name('event.scan');
        Route::view('/{event}/registration', 'events-registration')->name('event.registration');
    });

    // Knowledge
    Route::prefix('knowledge')->group(function () {
        Volt::route('/', 'knowledge.index')->name('knowledge');
        Volt::route('/articles', 'knowledge.article')->name('knowledge.articles');
        Volt::route('/articles/create', 'knowledge.article-create')->name('knowledge.articles-create');
        Volt::route('/articles/{slug}/edit', 'knowledge.article-create')->name('knowledge.articles-edit');
        Volt::route('/articles/{slug}', 'knowledge.article-show')->name('knowledge.articles-show');
        Volt::route('/announcements', 'knowledge.announcements')->name('knowledge.announcements');
        Volt::route('/policies', 'knowledge.policies')->name('knowledge.policies');
        Volt::route('/documentation', 'knowledge.documentations')->name('knowledge.documentation');
    });

    Route::view('/users', 'user-management')->name('users');

    // Project files (chunk upload proxy for progress + shorter requests)
    Route::post('project-files/upload-chunk', [ProjectFileChunkUploadController::class, 'uploadChunk'])
        ->name('project-files.upload-chunk');

    // Project files — direct multipart upload ke MinIO (control plane)
    Route::prefix('projects/{project}/files')->whereNumber('project')->group(function () {
        Route::post('uploads', [ProjectFileUploadController::class, 'initiate'])
            ->name('project-files.uploads.initiate');
        Route::post('uploads/{uploadId}/sign', [ProjectFileUploadController::class, 'sign'])
            ->middleware('throttle:60,1')
            ->name('project-files.uploads.sign');
        Route::post('uploads/{uploadId}/complete', [ProjectFileUploadController::class, 'complete'])
            ->name('project-files.uploads.complete');
        Route::delete('uploads/{uploadId}', [ProjectFileUploadController::class, 'abort'])
            ->name('project-files.uploads.abort');
    });

});

require __DIR__.'/settings.php';
