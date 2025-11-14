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
        Schema::create('payroll_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('employment_id')->constrained('employments')->onDelete('cascade');
            $table->string('ptkp_code'); // TK0, K0, K1, K2, K3, dst
            $table->boolean('has_npwp')->default(false);
            $table->json('tax_profile_json')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('employment_id');
            $table->index('ptkp_code');
            $table->index('active');
            // Note: One active payroll subject per employment should be enforced at application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_subjects');
    }
};
