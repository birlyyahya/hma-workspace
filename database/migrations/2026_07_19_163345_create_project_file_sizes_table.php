<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ukuran objek (bytes) per dokumen BEPM. BEPM tidak pernah tahu ukuran
     * file (byte diupload langsung browser → MinIO dan selalu melaporkan
     * "0 KB"), dan menanyakannya ke MinIO tiap render itu mahal — jadi ukuran
     * dicatat sekali di sini saat upload / lewat backfill.
     */
    public function up(): void
    {
        Schema::create('project_file_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('doc_id')->unique();
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_file_sizes');
    }
};
