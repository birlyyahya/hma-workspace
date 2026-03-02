<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Volt\Volt;
use App\Livewire\Events\EventShow;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

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
        Volt::route('/show/{id}', 'project.project-show')->name('projects.show');
    });

    // Izin
    Route::view('izin', 'izin')->name('izin');
    Volt::route('izin/{id}/detail', 'izin.izin-show')->name('izin.show');
    Volt::route('izin/{id}/pdf', 'izin.izin-show-pdf')->name('izin.pdf');
    Volt::route('izin/laporan-pengajuan', 'izin.laporan-pengajuan-izin')->name('izin.laporan-pengajuan');


    // Inventaris
    Route::view('inventaris', 'inventaris')->name('inventaris');

    // Chartered Accountants
    Route::view('chartered-accountants', 'charteredAccountants')->name('chartered-accountants');

    // Event
    Route::prefix('event')->group(function () {
        Route::view('/', 'events')->name('events');
        Route::get('/{event}', EventShow::class)->name('events.show');
        Route::view('chartered-accountants', 'charteredAccountants')->name('chartered-accountants');
        Route::view('/{event}/scan', 'events-scan')->name('event.scan');
        Route::view('/{event}/registration', 'events-registration')->name('event.registration');
    });

    // Knowledge
    Route::prefix('knowledge')->group(function () {
        Route::redirect('/', 'knowledge/articles');
        Volt::route('/articles', 'knowledge.article')->name('knowledge.articles');
        Volt::route('/announcements', 'knowledge.announcements')->name('knowledge.announcements');
        Volt::route('/policies', 'knowledge.policies')->name('knowledge.policies');
        Volt::route('/documentation', 'knowledge.documentations')->name('knowledge.documentation');
    });


    Route::view('/users', 'user-management')->name('users');
});

require __DIR__.'/settings.php';
