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
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('order_time')->nullable()->after('status')->default(NULL);
            $table->string('buyer_username')->nullable()->after('store_id')->default(NULL);
            $table->string('payment_method')->nullable()->after('status')->default(NULL);
            $table->text('notes')->nullable()->after('status')->default(NULL);
            $table->integer('commision_fee')->nullable()->after('total_price')->default(0);
            $table->integer('delivery_seller_protection_fee_premium_amount')->nullable()->after('total_price')->default(0);
            $table->integer('service_fee')->nullable()->after('total_price')->default(0);
            $table->integer('seller_order_processing_fee')->nullable()->after('total_price')->default(0);
            $table->integer('voucher_from_seller')->nullable()->after('total_price')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
