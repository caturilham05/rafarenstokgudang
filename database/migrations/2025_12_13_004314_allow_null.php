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
        Schema::table('products', function (Blueprint $table) {
            $table->bigInteger('product_online_id')->nullable()->default(0)->change();
            $table->bigInteger('product_model_id')->nullable()->default(0)->change();
            $table->integer('price')->nullable()->default(0)->change();
            $table->integer('sold')->nullable()->default(0)->change();
            $table->text('varian')->nullable()->default(null)->after('product_name');
            $table->text('url_product')->nullable()->default(null)->after('product_model_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
