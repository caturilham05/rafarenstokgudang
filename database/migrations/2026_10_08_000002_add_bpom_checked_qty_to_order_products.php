<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->unsignedInteger('bpom_checked_qty')->default(0)->after('is_bpom_checked');
        });

        DB::table('order_products')
            ->where('is_bpom_checked', true)
            ->update(['bpom_checked_qty' => 1]);

        DB::table('order_products')
            ->where('is_bpom_checked', true)
            ->where('qty', '>', 1)
            ->update(['is_bpom_checked' => false]);
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('bpom_checked_qty');
        });
    }
};
