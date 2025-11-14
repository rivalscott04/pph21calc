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
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->string('status')->default('draft'); // draft/reviewed/approved/posted
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index(['tenant_id', 'year', 'month']);
            $table->index('status');
            // Unique: one period per tenant per year-month
            $table->unique(['tenant_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
