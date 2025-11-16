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
            $table->string('link_hover')->default('#0ea5e9')->after('button');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_branding', function (Blueprint $table) {
            $table->dropColumn('link_hover');
        });
    }
};
