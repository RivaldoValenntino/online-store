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
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();

            $table->string('order_number')->unique();

            $table->decimal('subtotal', 30, 2)->default(0);
            $table->decimal('shipping_cost', 30, 2)->default(0);
            $table->decimal('discount', 30, 2)->default(0);
            $table->decimal('grand_total', 30, 2)->default(0);

            $table->string('status')->default('pending_payment');
            $table->string('payment_status')->default('pending');
            $table->string('shipping_status')->default('pending');
            $table->string('notes')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->text('shipping_address');

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
