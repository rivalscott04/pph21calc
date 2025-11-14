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
        Schema::create('person_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->uuid('person_id');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
            $table->foreignId('scheme_id')->constrained('identifier_schemes')->onDelete('cascade');
            $table->string('raw_value');
            $table->string('norm_value');
            $table->unsignedBigInteger('scope_entity_id')->nullable();
            $table->unsignedBigInteger('scope_org_unit_id')->nullable();
            $table->date('effective_start')->nullable();
            $table->date('effective_end')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            // Unique constraint: (tenant_id, scheme_id, norm_value, scope_entity_id)
            // Note: scope_entity_id can be null, so we need to handle that
            $table->unique(['tenant_id', 'scheme_id', 'norm_value', 'scope_entity_id'], 'person_identifiers_unique');
            
            $table->index('person_id');
            $table->index('scheme_id');
            $table->index('norm_value');
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_identifiers');
    }
};
