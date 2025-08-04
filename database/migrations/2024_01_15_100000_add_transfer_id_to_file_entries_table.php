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
        Schema::table('file_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('transfer_id')->nullable()->after('workspace_id');
            $table->string('hash')->nullable()->after('path');
            
            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');
            $table->index('transfer_id');
            $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_entries', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
            $table->dropIndex(['transfer_id']);
            $table->dropIndex(['hash']);
            $table->dropColumn(['transfer_id', 'hash']);
        });
    }
};
