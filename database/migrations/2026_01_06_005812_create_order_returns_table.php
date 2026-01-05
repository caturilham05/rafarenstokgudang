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
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->nullable();
            $table->index('order_id');
            $table->string('invoice_order')->nullable()->comment('invoice order');
            $table->index('invoice_order');
            $table->string('invoice_return')->nullable()->comment('invoice return');
            $table->index('invoice_return');
            $table->string('waybill')->nullable()->comment('tracking number');
            $table->index('waybill');
            $table->string('buyer_username')->nullable()->comment('username');
            $table->text('courier')->nullable()->comment('expedition');
            $table->string('reason')->nullable()->comment('reason');
            $table->text('reason_text')->nullable()->comment('reason description');
            $table->integer('refund_amount')->nullable()->default(0)->comment('refund amount');
            $table->dateTime('return_time')->nullable()->default(NULL)->comment('return time');
            $table->string('status')->nullable();
            $table->string('status_logistic')->nullable()->comment('status logistic');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
