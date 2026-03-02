<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();
            // contoh: spv, manager, gm, super-admin

            $table->string('name');
            // contoh: Supervisor, Manager

            $table->text('description')->nullable();

            $table->unsignedInteger('level')->default(1);
            // hirarki akses

            $table->string('scope')->nullable();
            // contoh: it-software, it-infra, hrd, global

            $table->boolean('can_approve')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
