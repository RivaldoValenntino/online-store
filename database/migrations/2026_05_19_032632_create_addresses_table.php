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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('label')->nullable(); // Rumah, Kantor, dll
            $table->string('recipient_name');
            $table->string('recipient_phone');

            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('subdistrict')->nullable();
            $table->string('postal_code')->nullable();

            $table->text('full_address');

            // buat RajaOngkir/Komship
            $table->string('province_id')->nullable();
            $table->string('city_id')->nullable();
            $table->string('district_id')->nullable();

            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
