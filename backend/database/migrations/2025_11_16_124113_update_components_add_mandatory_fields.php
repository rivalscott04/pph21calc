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
        Schema::table('components', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(false)->after('taxable');
            $table->integer('priority')->default(0)->after('is_mandatory');
            $table->boolean('is_active')->default(true)->after('priority');
            
            $table->index('is_mandatory');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropIndex(['is_mandatory']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['is_mandatory', 'priority', 'is_active']);
        });
    }
};
