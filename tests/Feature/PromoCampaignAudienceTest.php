<?php

namespace Tests\Feature;

use App\Jobs\DispatchPromoCampaignJob;
use App\Mail\PromoCampaignMail;
use App\Models\Customer;
use App\Models\PromoCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromoCampaignAudienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->boolean('is_active')->default(true);
                $table->boolean('unsubscribed_from_promos')->default(false);
                $table->boolean('email_marketing')->default(true);
                $table->boolean('in_app_marketing')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('promo_campaigns')) {
            Schema::create('promo_campaigns', function ($table) {
                $table->id();
                $table->foreignId('voucher_id')->nullable();
                $table->string('title');
                $table->string('subject')->nullable();
                $table->text('content')->nullable();
                $table->string('action_url')->nullable();
                $table->json('channels');
                $table->string('target_audience')->default('all');
                $table->json('target_customer_ids')->nullable();
                $table->string('status')->default('draft');
                $table->dateTime('scheduled_at')->nullable();
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->dateTime('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_channel_preferences_and_audience_dispatch(): void
    {
        Mail::fake();

        // 1. User belum subscribe email tetapi aktifkan in-app marketing
        $u1 = Customer::create([
            'name' => 'User 1 (In-App Only)',
            'email' => 'user1@example.com',
            'is_active' => true,
            'email_marketing' => false,
            'in_app_marketing' => true,
            'unsubscribed_from_promos' => false,
        ]);

        // 2. User aktifkan email marketing
        $u2 = Customer::create([
            'name' => 'User 2 (Both Channels)',
            'email' => 'user2@example.com',
            'is_active' => true,
            'email_marketing' => true,
            'in_app_marketing' => true,
            'unsubscribed_from_promos' => false,
        ]);

        // 3. User hanya aktifkan email marketing (in-app nonaktif)
        $u3 = Customer::create([
            'name' => 'User 3 (Email Only)',
            'email' => 'user3@example.com',
            'is_active' => true,
            'email_marketing' => true,
            'in_app_marketing' => false,
            'unsubscribed_from_promos' => false,
        ]);

        // 4. User menonaktifkan seluruh marketing notification
        $u4 = Customer::create([
            'name' => 'User 4 (No Marketing)',
            'email' => 'user4@example.com',
            'is_active' => true,
            'email_marketing' => false,
            'in_app_marketing' => false,
            'unsubscribed_from_promos' => false,
        ]);

        // 5. User unsubscribe email promo
        $u5 = Customer::create([
            'name' => 'User 5 (Unsubscribed Email)',
            'email' => 'user5@example.com',
            'is_active' => true,
            'email_marketing' => true,
            'in_app_marketing' => true,
            'unsubscribed_from_promos' => true,
        ]);

        // Campaign ke semua pengguna dengan kanal Email & In-App
        $campaign = PromoCampaign::create([
            'title' => 'Broadcast Promo All',
            'channels' => [PromoCampaign::CHANNEL_EMAIL, PromoCampaign::CHANNEL_IN_APP],
            'target_audience' => PromoCampaign::AUDIENCE_ALL,
            'content' => 'Promo Spesial Podo Raos',
            'status' => PromoCampaign::STATUS_DRAFT,
        ]);

        (new DispatchPromoCampaignJob($campaign->id))->handle();

        // 1. User yang belum subscribe email tetapi mengaktifkan in-app marketing tetap menerima in-app promo.
        $this->assertEquals(1, $u1->notifications()->where('data->campaign_id', $campaign->id)->count());
        Mail::assertNotQueued(PromoCampaignMail::class, fn($m) => $m->customer->id === $u1->id);

        // 2. User yang mengaktifkan email marketing menerima email promo.
        Mail::assertQueued(PromoCampaignMail::class, fn($m) => $m->customer->id === $u2->id);

        // 3. User yang hanya mengaktifkan email marketing tidak menerima in-app promo.
        $this->assertEquals(0, $u3->notifications()->where('data->campaign_id', $campaign->id)->count());
        Mail::assertQueued(PromoCampaignMail::class, fn($m) => $m->customer->id === $u3->id);

        // 4. User yang menonaktifkan seluruh marketing notification tidak menerima promo.
        $this->assertEquals(0, $u4->notifications()->where('data->campaign_id', $campaign->id)->count());
        Mail::assertNotQueued(PromoCampaignMail::class, fn($m) => $m->customer->id === $u4->id);

        // 5. User yang unsubscribe email tidak menerima email campaign berikutnya.
        Mail::assertNotQueued(PromoCampaignMail::class, fn($m) => $m->customer->id === $u5->id);
        $this->assertEquals(1, $u5->notifications()->where('data->campaign_id', $campaign->id)->count());

        // 6. Campaign dengan target subscriber saja tetap hanya mengirim kepada subscriber.
        Mail::fake();
        $subCampaign = PromoCampaign::create([
            'title' => 'Khusus Subscriber',
            'channels' => [PromoCampaign::CHANNEL_EMAIL, PromoCampaign::CHANNEL_IN_APP],
            'target_audience' => PromoCampaign::AUDIENCE_SUBSCRIBERS,
            'content' => 'Newsletter Eksklusif',
            'status' => PromoCampaign::STATUS_DRAFT,
        ]);

        (new DispatchPromoCampaignJob($subCampaign->id))->handle();

        $this->assertEquals(0, $u1->notifications()->where('data->campaign_id', $subCampaign->id)->count());
        Mail::assertQueued(PromoCampaignMail::class, fn($m) => $m->customer->id === $u2->id);
        Mail::assertQueued(PromoCampaignMail::class, fn($m) => $m->customer->id === $u3->id);

        // 7. Tidak ada duplikasi penerima dalam satu campaign.
        $campaign->update(['status' => PromoCampaign::STATUS_SCHEDULED]);
        (new DispatchPromoCampaignJob($campaign->id))->handle();
        $this->assertEquals(1, $u1->notifications()->where('data->campaign_id', $campaign->id)->count());
    }
}
