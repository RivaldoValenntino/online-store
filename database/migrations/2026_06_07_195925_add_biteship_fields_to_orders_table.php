<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shipping_provider')) {
                $table->string('shipping_provider')
                    ->default('biteship')
                    ->after('shipping_cost');
            }

            if (! Schema::hasColumn('orders', 'shipping_service_name')) {
                $table->string('shipping_service_name')
                    ->nullable()
                    ->after('shipping_service');
            }

            if (! Schema::hasColumn('orders', 'biteship_order_id')) {
                $table->string('biteship_order_id')
                    ->nullable()
                    ->after('shipping_status');
            }

            if (! Schema::hasColumn('orders', 'biteship_waybill_id')) {
                $table->string('biteship_waybill_id')
                    ->nullable()
                    ->after('biteship_order_id');
            }

            if (! Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')
                    ->nullable()
                    ->after('biteship_waybill_id');
            }

            if (! Schema::hasColumn('orders', 'shipping_label_url')) {
                $table->text('shipping_label_url')
                    ->nullable()
                    ->after('tracking_number');
            }

            if (! Schema::hasColumn('orders', 'shipment_created_at')) {
                $table->timestamp('shipment_created_at')
                    ->nullable()
                    ->after('shipping_label_url');
            }

            if (! Schema::hasColumn('orders', 'shipped_at')) {
                $table->timestamp('shipped_at')
                    ->nullable()
                    ->after('shipment_created_at');
            }

            if (! Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')
                    ->nullable()
                    ->after('shipped_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'shipping_provider',
                'shipping_service_name',
                'biteship_order_id',
                'biteship_waybill_id',
                'tracking_number',
                'shipping_label_url',
                'shipment_created_at',
                'shipped_at',
                'delivered_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
