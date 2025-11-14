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
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('code');
            $table->string('name');
            $table->string('type')->nullable(); // HQ/REGION/BRANCH/KCP/UNIT
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            
            // Self-referencing foreign key for tree structure
            $table->foreign('parent_id')->references('id')->on('org_units')->onDelete('cascade');
            
            $table->index('tenant_id');
            $table->index('parent_id');
            $table->index('type');
            // Unique: code must be unique per tenant
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
