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
            $table->foreignId('voucher_id')->nullable()->after('discount')->constrained()->nullOnDelete();
            $table->string('voucher_code')->nullable()->after('voucher_id');
            $table->string('voucher_discount_type')->nullable()->after('voucher_code');
            $table->decimal('product_discount', 12, 2)->default(0)->after('voucher_discount_type');
            $table->decimal('shipping_discount', 12, 2)->default(0)->after('product_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn([
                'voucher_id',
                'voucher_code',
                'voucher_discount_type',
                'product_discount',
                'shipping_discount',
            ]);
        });
    }
};
