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
        Schema::create('transfer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->onDelete('cascade');
            $table->string('original_name');
            $table->bigInteger('size');
            $table->string('mime_type')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('upload_key')->nullable(); // TUS upload key for tracking
            $table->timestamps();
            
            $table->index(['transfer_id']);
            $table->index(['upload_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_files');
    }
};
