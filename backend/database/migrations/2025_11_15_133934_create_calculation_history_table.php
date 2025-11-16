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
        Schema::create('calculation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('employment_id')->nullable()->constrained('employments')->onDelete('set null');
            $table->string('person_name')->nullable(); // Store name in case employment is deleted
            $table->string('ptkp_code', 10);
            $table->boolean('has_npwp')->default(true);
            $table->integer('year');
            $table->integer('month');
            $table->decimal('bruto', 15, 2)->default(0);
            $table->decimal('biaya_jabatan', 15, 2)->default(0);
            $table->decimal('iuran_pensiun', 15, 2)->default(0);
            $table->decimal('zakat', 15, 2)->default(0);
            $table->decimal('neto_masa', 15, 2)->default(0);
            $table->decimal('ptkp_yearly', 15, 2)->default(0);
            $table->decimal('pkp_annualized', 15, 2)->default(0);
            $table->decimal('pph21_masa', 15, 2)->default(0);
            $table->json('notes')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('employment_id');
            $table->index(['year', 'month']);
            $table->index(['user_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculation_history');
    }
};
