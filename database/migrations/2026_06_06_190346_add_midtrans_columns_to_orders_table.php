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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->after('order_number');
            $table->string('midtrans_snap_token')->nullable()->after('midtrans_order_id');
            $table->text('midtrans_redirect_url')->nullable()->after('midtrans_snap_token');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
