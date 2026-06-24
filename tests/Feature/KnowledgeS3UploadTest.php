<?php

use App\Models\Role;
use App\Models\SupportDocumentation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('documentation upload is stored on the default s3 disk', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);

    Volt::actingAs($admin)
        ->test('knowledge.documentations')
        ->set('title', 'Panduan Penggunaan')
        ->set('content', 'Isi dokumentasi')
        ->set('file', UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    $doc = SupportDocumentation::first();

    expect($doc)->not->toBeNull();
    expect($doc->file)->toStartWith('knowledge/documentations/');

    Storage::disk('s3')->assertExists($doc->file);
});
