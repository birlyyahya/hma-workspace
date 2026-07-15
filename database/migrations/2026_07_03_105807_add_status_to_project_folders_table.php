<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda operasi async pada folder: 'moving' saat MoveProjectFilesJob /
     * DeleteProjectFilesJob sedang memproses subtree-nya, supaya UI mengunci
     * operasi lain pada folder tersebut. Null = idle.
     */
    public function up(): void
    {
        Schema::table('project_folders', function (Blueprint $table) {
            $table->string('status')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('project_folders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
