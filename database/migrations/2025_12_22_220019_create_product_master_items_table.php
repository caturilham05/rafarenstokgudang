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
        Schema::create('product_master_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_master_id')->unsigned();
            $table->index('product_master_id');
            $table->bigInteger('product_id')->unsigned();
            $table->index('product_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_master_items');
    }
};
