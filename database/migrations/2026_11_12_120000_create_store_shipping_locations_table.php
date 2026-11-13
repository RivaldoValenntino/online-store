<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_shipping_locations')) {
            Schema::create('store_shipping_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('contact_name');
                $table->string('contact_phone', 30);
                $table->text('address');
                $table->string('province')->nullable();
                $table->string('city')->nullable();
                $table->string('district')->nullable();
                $table->string('subdistrict')->nullable();
                $table->string('postal_code', 10)->nullable();
                $table->string('biteship_area_id');
                $table->string('biteship_area_label')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->text('courier_notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index('is_active');
                $table->index('is_default');
                $table->index('biteship_area_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_shipping_locations');
    }
};
