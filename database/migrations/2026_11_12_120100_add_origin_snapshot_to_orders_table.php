<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'origin_location_name')) {
                $table->string('origin_location_name')->nullable()->after('recipient_phone');
            }

            if (! Schema::hasColumn('orders', 'origin_contact_name')) {
                $table->string('origin_contact_name')->nullable()->after('origin_location_name');
            }

            if (! Schema::hasColumn('orders', 'origin_contact_phone')) {
                $table->string('origin_contact_phone', 30)->nullable()->after('origin_contact_name');
            }

            if (! Schema::hasColumn('orders', 'origin_address')) {
                $table->text('origin_address')->nullable()->after('origin_contact_phone');
            }

            if (! Schema::hasColumn('orders', 'origin_postal_code')) {
                $table->string('origin_postal_code', 10)->nullable()->after('origin_address');
            }

            if (! Schema::hasColumn('orders', 'origin_area_id')) {
                $table->string('origin_area_id')->nullable()->after('origin_postal_code');
            }

            if (! Schema::hasColumn('orders', 'origin_latitude')) {
                $table->decimal('origin_latitude', 10, 8)->nullable()->after('origin_area_id');
            }

            if (! Schema::hasColumn('orders', 'origin_longitude')) {
                $table->decimal('origin_longitude', 11, 8)->nullable()->after('origin_latitude');
            }

            if (! Schema::hasColumn('orders', 'origin_note')) {
                $table->text('origin_note')->nullable()->after('origin_longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'origin_location_name',
                'origin_contact_name',
                'origin_contact_phone',
                'origin_address',
                'origin_postal_code',
                'origin_area_id',
                'origin_latitude',
                'origin_longitude',
                'origin_note',
            ]);
        });
    }
};
