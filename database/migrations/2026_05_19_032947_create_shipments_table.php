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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('provider')->default('komship');
            $table->string('courier_code')->nullable();
            $table->string('courier_service')->nullable();
            $table->string('courier_name')->nullable();

            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->integer('weight_gram')->default(0);

            $table->string('waybill_number')->nullable();
            $table->string('tracking_status')->default('pending');
            $table->string('label_url')->nullable();

            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
