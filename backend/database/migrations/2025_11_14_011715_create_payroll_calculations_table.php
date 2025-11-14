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
        Schema::create('payroll_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('employment_id')->constrained('employments')->onDelete('cascade');
            $table->foreignId('period_id')->constrained('periods')->onDelete('cascade');
            $table->decimal('bruto', 15, 2)->default(0);
            $table->decimal('biaya_jabatan', 15, 2)->default(0);
            $table->decimal('iuran_pensiun', 15, 2)->default(0);
            $table->decimal('zakat', 15, 2)->default(0);
            $table->decimal('neto_masa', 15, 2)->default(0);
            $table->decimal('ptkp_yearly', 15, 2)->default(0);
            $table->decimal('pkp_annualized', 15, 2)->default(0);
            $table->decimal('pph21_masa', 15, 2)->default(0);
            $table->decimal('pph21_ytd', 15, 2)->default(0);
            $table->decimal('pph21_settlement_dec', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('employment_id');
            $table->index('period_id');
            // Unique: one calculation per employment per period
            $table->unique(['employment_id', 'period_id']);
            // Index for quick lookup by period
            $table->index(['period_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_calculations');
    }
};
