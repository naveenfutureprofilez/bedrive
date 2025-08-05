<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->timestamp('expiry_at')->nullable();
            $table->index('expiry_at');
        });

        DB::table('transfers')->update([
            'expiry_at' => DB::raw('expires_at')
        ]);

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable();
            $table->index('expires_at');
        });

        DB::table('transfers')->update([
            'expires_at' => DB::raw('expiry_at')
        ]);

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('expiry_at');
        });
    }
};

