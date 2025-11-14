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
        Schema::create('deductions_manual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('employment_id')->constrained('employments')->onDelete('cascade');
            $table->foreignId('period_id')->constrained('periods')->onDelete('cascade');
            $table->string('type'); // iuran_pensiun/zakat/lainnya
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('employment_id');
            $table->index('period_id');
            $table->index('type');
            // Index for quick lookup by period and employment
            $table->index(['period_id', 'employment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deductions_manual');
    }
};
