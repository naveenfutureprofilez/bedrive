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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 12)->unique();
            $table->string('sender_email')->nullable();
            $table->string('sender_name')->nullable();
            $table->json('recipient_emails')->nullable();
            $table->text('message')->nullable();
            $table->string('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('download_count')->default(0);
            $table->integer('max_downloads')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_password_protected')->default(false);
            $table->bigInteger('total_size')->default(0);
            $table->enum('status', ['pending', 'completed', 'expired', 'deleted'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('hash');
            $table->index('expires_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
