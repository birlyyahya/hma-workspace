<?php

use Livewire\Volt\Volt;
use App\Livewire\Events\EventShow;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::view('projects', 'projects')->name('projects');
    Route::view('events', 'events')->name('events');
    Route::get('events/{event}', EventShow::class)->name('events.show');
    Route::view('chartered-accountants', 'charteredAccountants')->name('chartered-accountants');
    Route::view('events/{event}/scan', 'events-scan')->name('event.scan');
    Route::view('events/{event}/registration', 'events-registration')->name('event.registration');
});

require __DIR__.'/settings.php';
