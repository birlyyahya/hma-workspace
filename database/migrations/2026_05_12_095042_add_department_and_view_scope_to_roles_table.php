<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('level')
                    ->constrained('departments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });
    }
};
