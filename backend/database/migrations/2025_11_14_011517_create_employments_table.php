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
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->uuid('person_id');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
            $table->foreignId('org_unit_id')->constrained('org_units')->onDelete('cascade');
            $table->string('employment_type'); // tetap/tidak_tetap/harian/tenaga_ahli
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('primary_payroll')->default(false);
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('person_id');
            $table->index('org_unit_id');
            $table->index('employment_type');
            $table->index('primary_payroll');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employments');
    }
};
