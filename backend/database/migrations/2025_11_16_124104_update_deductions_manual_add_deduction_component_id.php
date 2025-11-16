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
        Schema::table('deductions_manual', function (Blueprint $table) {
            // Add new column (nullable first to allow existing data)
            $table->foreignId('deduction_component_id')
                ->nullable()
                ->after('period_id')
                ->constrained('deduction_components')
                ->onDelete('cascade');
            
            $table->index('deduction_component_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deductions_manual', function (Blueprint $table) {
            $table->dropForeign(['deduction_component_id']);
            $table->dropIndex(['deduction_component_id']);
            $table->dropColumn('deduction_component_id');
        });
    }
};
