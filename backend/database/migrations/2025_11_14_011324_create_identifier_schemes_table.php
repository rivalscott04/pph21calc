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
        Schema::create('identifier_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->string('code'); // BANK_EMP_ID, BUMN_9D, dst
            $table->string('label');
            $table->string('entity_type')->nullable(); // BANK/BUMN/KAMPUS/LAINNYA
            $table->string('regex_pattern')->nullable();
            $table->integer('length_min')->nullable();
            $table->integer('length_max')->nullable();
            $table->string('normalize_rule')->default('NONE'); // NUMERIC/ALNUM/UPPER/NONE
            $table->string('example')->nullable();
            $table->string('checksum_type')->default('NONE'); // LUHN/MOD_N/NONE
            $table->timestamps();
            
            // Unique: code must be unique per tenant (or globally if tenant_id is null)
            $table->unique(['tenant_id', 'code']);
            $table->index('entity_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identifier_schemes');
    }
};
