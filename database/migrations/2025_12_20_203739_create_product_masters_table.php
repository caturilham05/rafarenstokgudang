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
        Schema::create('product_masters', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->integer('stock')->nullable()->default(0)->unsigned();
            $table->integer('stock_conversion')->nullable()->unsigned()->default(0)->comment('untuk menentukan berapa stock yang berkurang saat ada order');
            $table->integer('sale')->nullable()->default(0)->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_masters');
    }
};
