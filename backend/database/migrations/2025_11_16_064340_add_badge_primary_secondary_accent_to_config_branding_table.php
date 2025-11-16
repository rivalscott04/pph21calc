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
            $table->string('badge_primary')->default('#0ea5e9')->after('toast_error'); // HEX color, default sky-500
            $table->string('badge_secondary')->default('#10b981')->after('badge_primary'); // HEX color, default emerald-600
            $table->string('badge_accent')->default('#f59e0b')->after('badge_secondary'); // HEX color, default amber-500
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_branding', function (Blueprint $table) {
            $table->dropColumn(['badge_primary', 'badge_secondary', 'badge_accent']);
        });
    }
};
