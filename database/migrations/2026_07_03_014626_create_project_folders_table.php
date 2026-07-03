<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Folder tree lokal untuk project files. `project_id` merujuk project di
     * BEPM (API eksternal) sehingga sengaja TANPA foreign key constraint.
     * Catatan MySQL: unique index mengizinkan banyak NULL pada parent_id,
     * jadi keunikan nama folder di level root tetap divalidasi di aplikasi.
     */
    public function up(): void
    {
        Schema::create('project_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->foreignId('parent_id')->nullable()->constrained('project_folders')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['project_id', 'parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_folders');
    }
};
