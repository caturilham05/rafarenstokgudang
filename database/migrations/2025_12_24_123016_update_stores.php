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
        Schema::table('stores', function (Blueprint $table) {
            $table->dateTime('refresh_token_expires_at')->nullable()->after('token_expires_at');
            $table->text('chiper')->nullable()->after('refresh_token');
            $table->text('app_key')->nullable()->after('refresh_token_expires_at');
            $table->text('app_secret')->nullable()->after('refresh_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            //
        });
    }
};
