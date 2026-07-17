<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapping dokumen BEPM → folder virtual workspace. Baris hanya ada bila
     * file berada DI DALAM folder; file di root tidak punya baris. Dengan ini
     * lokasi file tidak lagi diturunkan dari object key MinIO (key baru flat:
     * projects_docs/{tahun}/{project}/{nama}).
     */
    public function up(): void
    {
        Schema::create('project_folder_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('doc_id')->unique();
            $table->foreignId('project_folder_id')->constrained('project_folders')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_folder_files');
    }
};
