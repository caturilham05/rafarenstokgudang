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
        Schema::table('order_products', function (Blueprint $table) {
            $table->bigInteger('product_online_id')->nullable()->after('product_id')->default(0);
            $table->bigInteger('product_model_id')->nullable()->after('product_id')->default(0);
            $table->string('product_name')->nullable()->after('product_id')->default(NULL);
            $table->string('varian')->nullable()->after('product_id')->default(NULL);
            $table->index('product_online_id');
            $table->index('product_model_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            //
        });
    }
};
