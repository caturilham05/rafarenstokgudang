<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_product_bpom_scans')) {
            Schema::create('order_product_bpom_scans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_master_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('scanned_qty')->default(0);
            });
        }

        if (!Schema::hasIndex('order_product_bpom_scans', 'op_bpom_scans_unique')) {
            Schema::table('order_product_bpom_scans', function (Blueprint $table) {
                $table->unique(['order_product_id', 'product_master_id'], 'op_bpom_scans_unique');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('order_product_bpom_scans');
    }
};
