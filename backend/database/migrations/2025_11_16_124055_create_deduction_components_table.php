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
        Schema::create('deduction_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->enum('type', ['mandatory', 'custom'])->default('custom');
            $table->enum('calculation_type', ['auto', 'manual', 'percentage'])->default('manual');
            $table->boolean('is_tax_deductible')->default(true);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('type');
            $table->index('is_active');
            // Unique: code must be unique per tenant
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_components');
    }
};
