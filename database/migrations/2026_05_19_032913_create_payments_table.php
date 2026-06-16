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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('payment_gateway')->default('midtrans');
            $table->string('gateway_order_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('snap_token')->nullable();
            $table->string('redirect_url')->nullable();

            $table->string('payment_type')->nullable();
            $table->string('transaction_status')->default('pending');
            $table->string('fraud_status')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
