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
        Schema::table('identifier_schemes', function (Blueprint $table) {
            // Tambah field prefix
            $table->string('prefix')->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('identifier_schemes', function (Blueprint $table) {
            $table->dropColumn('prefix');
        });
    }
};
