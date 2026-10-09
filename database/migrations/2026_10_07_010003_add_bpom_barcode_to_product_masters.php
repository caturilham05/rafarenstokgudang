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
        Schema::table('product_masters', function (Blueprint $table) {
            $table->string('bpom_barcode')->nullable()->unique()->after('product_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_masters', function (Blueprint $table) {
            $table->dropColumn('bpom_barcode');
        });
    }
};
