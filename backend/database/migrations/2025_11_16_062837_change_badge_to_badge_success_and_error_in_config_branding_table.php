<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get existing badge values before dropping column
        $existingBadges = DB::table('config_branding')->pluck('badge', 'id');
        
        Schema::table('config_branding', function (Blueprint $table) {
            // Drop old badge column
            $table->dropColumn('badge');
            
            // Add new badge_success and badge_error columns
            $table->string('badge_success')->default('#10b981')->after('button'); // HEX color, default green
            $table->string('badge_error')->default('#ef4444')->after('badge_success'); // HEX color, default red
        });
        
        // Migrate existing badge values to badge_success (for backward compatibility)
        // badge_error will use default red color
        foreach ($existingBadges as $id => $badgeValue) {
            DB::table('config_branding')
                ->where('id', $id)
                ->update(['badge_success' => $badgeValue]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get badge_success values before dropping columns
        $badgeSuccessValues = DB::table('config_branding')->pluck('badge_success', 'id');
        
        Schema::table('config_branding', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['badge_success', 'badge_error']);
            
            // Restore old badge column
            $table->string('badge')->default('#3d4451')->after('button');
        });
        
        // Restore badge values from badge_success
        foreach ($badgeSuccessValues as $id => $badgeValue) {
            DB::table('config_branding')
                ->where('id', $id)
                ->update(['badge' => $badgeValue]);
        }
    }
};
