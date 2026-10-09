<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('bpom_user_id')->nullable()->after('bpom_checked_at');
            $table->string('bpom_user_name')->nullable()->after('bpom_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bpom_user_id', 'bpom_user_name']);
        });
    }
};
