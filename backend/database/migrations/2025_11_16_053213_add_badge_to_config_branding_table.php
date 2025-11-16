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
        Schema::table('config_branding', function (Blueprint $table) {
            $table->string('badge')->default('#3d4451')->after('button'); // HEX color, default same as neutral
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_branding', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
