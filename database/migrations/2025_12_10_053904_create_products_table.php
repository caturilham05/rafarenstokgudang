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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('store_id')->unsigned();
            $table->foreign('store_id')->references('id')->on('stores')->onUpdate('cascade')->onDelete('cascade');
            $table->index('store_id');
            $table->string('product_online_id')->comment('ID produk dari platform online');
            $table->string('product_model_id')->comment('ID varian produk dari platform online');
            $table->string('product_name')->comment('Nama produk');
            $table->string('price')->comment('HPP produk');
            $table->string('sale')->comment('Harga jual produk');
            $table->integer('stock')->default(0);
            $table->integer('sold')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
