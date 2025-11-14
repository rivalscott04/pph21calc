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
        Schema::create('coretax_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('period_id')->constrained('periods')->onDelete('cascade');
            $table->json('payload_json');
            $table->string('status')->default('pending'); // pending/sent/validated/failed
            $table->string('ref_no')->nullable();
            $table->json('response_json')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('period_id');
            $table->index('status');
            $table->index('ref_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coretax_logs');
    }
};
