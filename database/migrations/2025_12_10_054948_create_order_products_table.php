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
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->unsigned();
            $table->foreign('order_id')->references('id')->on('orders')->onUpdate('cascade')->onDelete('cascade');
            $table->index('order_id');
            $table->bigInteger('product_id')->unsigned();
            $table->index('product_id');
            $table->integer('qty')->default(0)->comment('Jumlah produk dalam pesanan');
            $table->integer('price')->default(0)->comment('hpp produk saat pesanan dibuat');
            $table->integer('sale')->default(0)->comment('harga jual produk saat pesanan dibuat');
            $table->integer('discount')->default(0)->comment('diskon produk dalam pesanan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};
