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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice')->comment('Nomor invoice pesanan');
            $table->bigInteger('store_id')->unsigned();
            $table->index('store_id');
            $table->string('customer_name')->comment('Nama pelanggan');
            $table->string('customer_phone')->comment('Nomor telepon pelanggan');
            $table->string('customer_address')->comment('Alamat pelanggan');
            $table->string('courier')->comment('Nama kurir pengiriman');
            $table->integer('qty')->default(0)->comment('Jumlah produk dalam pesanan');
            $table->integer('discount')->default(0)->comment('Total diskon untuk pesanan');
            $table->integer('shipping_cost')->default(0)->comment('Biaya pengiriman');
            $table->integer('total_price')->default(0)->comment('Total harga pesanan setelah diskon dan ongkir');
            $table->string('status')->default('pending')->comment('Status pesanan (pending, processed, shipped, delivered, cancelled)');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
