<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_policies', function (Blueprint $table) {
            $table->string('file')->nullable()->after('content');
        });

        Schema::table('support_documentations', function (Blueprint $table) {
            $table->string('file')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('support_policies', function (Blueprint $table) {
            $table->dropColumn('file');
        });

        Schema::table('support_documentations', function (Blueprint $table) {
            $table->dropColumn('file');
        });
    }
};
