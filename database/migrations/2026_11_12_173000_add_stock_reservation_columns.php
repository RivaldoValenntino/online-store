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
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'reserved_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('reserved_stock')->default(0)->after('stock');
            });
        }

        if (Schema::hasTable('product_variants') && ! Schema::hasColumn('product_variants', 'reserved_stock')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->unsignedInteger('reserved_stock')->default(0)->after('stock');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'stock_reserved_at')) {
                    $table->timestamp('stock_reserved_at')->nullable()->after('payment_expires_at');
                }
                if (! Schema::hasColumn('orders', 'stock_deducted_at')) {
                    $table->timestamp('stock_deducted_at')->nullable()->after('stock_reserved_at');
                }
                if (! Schema::hasColumn('orders', 'stock_released_at')) {
                    $table->timestamp('stock_released_at')->nullable()->after('stock_deducted_at');
                }
                if (! Schema::hasColumn('orders', 'stock_admin_review_required')) {
                    $table->boolean('stock_admin_review_required')->default(false)->after('stock_released_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'reserved_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('reserved_stock');
            });
        }

        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'reserved_stock')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('reserved_stock');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $columns = array_filter([
                    Schema::hasColumn('orders', 'stock_reserved_at') ? 'stock_reserved_at' : null,
                    Schema::hasColumn('orders', 'stock_deducted_at') ? 'stock_deducted_at' : null,
                    Schema::hasColumn('orders', 'stock_released_at') ? 'stock_released_at' : null,
                    Schema::hasColumn('orders', 'stock_admin_review_required') ? 'stock_admin_review_required' : null,
                ]);

                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
