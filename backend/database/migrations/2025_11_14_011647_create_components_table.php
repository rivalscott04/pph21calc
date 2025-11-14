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
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('code');
            $table->string('name');
            $table->string('group'); // gaji_pokok/tunjangan/bonus/lembur/natura/lainnya
            $table->boolean('taxable')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('group');
            $table->index('taxable');
            // Unique: code must be unique per tenant
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
