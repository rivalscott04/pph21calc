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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('table_name');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('action'); // insert/update/delete
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('table_name');
            $table->index('action');
            $table->index('created_at');
            // Composite index for common queries
            $table->index(['tenant_id', 'table_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
