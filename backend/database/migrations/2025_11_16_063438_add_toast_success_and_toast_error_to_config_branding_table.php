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
            $table->string('toast_success')->default('#10b981')->after('badge_error'); // HEX color, default green
            $table->string('toast_error')->default('#ef4444')->after('toast_success'); // HEX color, default red
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_branding', function (Blueprint $table) {
            $table->dropColumn(['toast_success', 'toast_error']);
        });
    }
};
