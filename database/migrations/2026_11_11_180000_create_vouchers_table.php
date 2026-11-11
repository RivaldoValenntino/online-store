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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // percentage, fixed, free_shipping, shipping_discount
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('min_spend', 12, 2)->default(0);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->unsignedInteger('total_quota')->nullable();
            $table->unsignedInteger('total_used')->default(0);
            $table->unsignedInteger('user_limit')->default(1);
            $table->unsignedInteger('transaction_limit')->default(1);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('target_audience')->default('all'); // all, new_users, specific_users
            $table->json('eligible_category_ids')->nullable();
            $table->json('eligible_product_ids')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index(['is_active', 'starts_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
