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
        Schema::create('config_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->onDelete('cascade');
            $table->string('primary')->default('#0ea5e9'); // HEX color
            $table->string('secondary')->default('#10b981'); // HEX color
            $table->string('accent')->default('#f59e0b'); // HEX color
            $table->string('neutral')->default('#3d4451'); // HEX color
            $table->string('base100')->default('#ffffff'); // HEX color
            $table->timestamps();
            
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_branding');
    }
};
