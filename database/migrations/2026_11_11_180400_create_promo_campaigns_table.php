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
        Schema::create('promo_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->string('action_url')->nullable();
            $table->json('channels'); // ['banner', 'email', 'in_app']
            $table->string('target_audience')->default('all'); // all, purchased, non_purchased, specific
            $table->string('status')->default('draft'); // draft, scheduled, sending, completed, failed
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_campaigns');
    }
};
