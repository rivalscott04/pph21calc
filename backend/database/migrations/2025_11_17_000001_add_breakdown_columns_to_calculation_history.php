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
        Schema::table('calculation_history', function (Blueprint $table) {
            if (!Schema::hasColumn('calculation_history', 'calculation_mode')) {
                $table->string('calculation_mode', 20)
                    ->default('monthly')
                    ->after('month');
            }

            if (!Schema::hasColumn('calculation_history', 'earnings_breakdown')) {
                $table->json('earnings_breakdown')
                    ->nullable()
                    ->after('notes');
            }

            if (!Schema::hasColumn('calculation_history', 'deductions_breakdown')) {
                $table->json('deductions_breakdown')
                    ->nullable()
                    ->after('earnings_breakdown');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculation_history', function (Blueprint $table) {
            if (Schema::hasColumn('calculation_history', 'deductions_breakdown')) {
                $table->dropColumn('deductions_breakdown');
            }

            if (Schema::hasColumn('calculation_history', 'earnings_breakdown')) {
                $table->dropColumn('earnings_breakdown');
            }

            if (Schema::hasColumn('calculation_history', 'calculation_mode')) {
                $table->dropColumn('calculation_mode');
            }
        });
    }
};

