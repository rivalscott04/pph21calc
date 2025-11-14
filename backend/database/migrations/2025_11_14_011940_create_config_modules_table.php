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
        Schema::create('config_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->onDelete('cascade');
            $table->boolean('core_payroll')->default(true);
            $table->boolean('coretax_integration')->default(false);
            $table->boolean('compliance_ojk')->default(false);
            $table->boolean('compliance_pdp')->default(false);
            $table->boolean('audit_trail')->default(true);
            $table->boolean('bpjs_integration')->default(false);
            $table->boolean('syariah_extension')->default(false);
            $table->timestamps();
            
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_modules');
    }
};
