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
        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('review');
                $table->string('status')->default('approved')->index();
                $table->boolean('is_verified_purchase')->default(true);
                $table->timestamps();

                $table->index(['product_id', 'status']);
                $table->index(['customer_id']);
                $table->index(['rating']);
            });
        }

        if (! Schema::hasTable('product_review_images')) {
            Schema::create('product_review_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_review_id')->constrained('product_reviews')->cascadeOnDelete();
                $table->string('image_path');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_review_replies')) {
            Schema::create('product_review_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_review_id')->unique()->constrained('product_reviews')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reply');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'rating_avg')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('rating_avg', 3, 2)->default(0.00)->after('price');
                $table->unsignedInteger('reviews_count')->default(0)->after('rating_avg');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'rating_avg')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['rating_avg', 'reviews_count']);
            });
        }

        Schema::dropIfExists('product_review_replies');
        Schema::dropIfExists('product_review_images');
        Schema::dropIfExists('product_reviews');
    }
};
