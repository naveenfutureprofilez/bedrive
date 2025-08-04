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
        Schema::table('transfers', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->renameColumn('expires_at', 'expiry_at');
            $table->string('password_hash')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
        
        // Modify status enum in a separate statement
        DB::statement("ALTER TABLE transfers MODIFY status ENUM('uploading', 'completed', 'failed', 'expired') DEFAULT 'uploading'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('uuid');
            $table->renameColumn('expiry_at', 'expires_at');
            $table->dropColumn('password_hash');
            $table->dropColumn('completed_at');
        });
        
        // Revert status enum
        DB::statement("ALTER TABLE transfers MODIFY status ENUM('pending', 'completed', 'expired', 'deleted') DEFAULT 'pending'");
    }
};
