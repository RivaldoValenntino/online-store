<?php

namespace Tests\Unit;

use App\Jobs\DispatchPromoCampaignJob;
use App\Mail\PromoCampaignMail;
use App\Models\Customer;
use App\Models\PromoCampaign;
use App\Notifications\PromoNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\TestCase;

class PromoCampaignAudienceTest extends TestCase
{
    public function test_customer_preferences_logic(): void
    {
        // 1. User belum subscribe email tetapi aktifkan in-app marketing
        $user1 = new Customer([
            'is_active' => true,
            'email_marketing' => false,
            'in_app_marketing' => true,
            'unsubscribed_from_promos' => false,
        ]);
        $this->assertFalse($user1->canReceiveEmailMarketing());
        $this->assertTrue($user1->canReceiveInAppMarketing());

        // 2. User aktifkan email marketing
        $user2 = new Customer([
            'is_active' => true,
            'email_marketing' => true,
            'in_app_marketing' => true,
            'unsubscribed_from_promos' => false,
        ]);
        $this->assertTrue($user2->canReceiveEmailMarketing());
        $this->assertTrue($user2->canReceiveInAppMarketing());

        // 3. User hanya aktifkan email marketing (in-app nonaktif)
        $user3 = new Customer([
            'is_active' => true,
            'email_marketing' => true,
            'in_app_marketing' => false,
            'unsubscribed_from_promos' => false,
        ]);
        $this->assertTrue($user3->canReceiveEmailMarketing());
        $this->assertFalse($user3->canReceiveInAppMarketing());

        // 4. User menonaktifkan seluruh marketing notification
        $user4 = new Customer([
            'is_active' => true,
            'email_marketing' => false,
            'in_app_marketing' => false,
            'unsubscribed_from_promos' => false,
        ]);
        $this->assertFalse($user4->canReceiveEmailMarketing());
        $this->assertFalse($user4->canReceiveInAppMarketing());

        // 5. User unsubscribe email promo
        $user5 = new Customer([
            'is_active' => true,
            'email_marketing' => true,
            'in_app_marketing' => true,
            'unsubscribed_from_promos' => true,
        ]);
        $this->assertFalse($user5->canReceiveEmailMarketing());
        $this->assertTrue($user5->canReceiveInAppMarketing()); // in-app tetap aktif jika hanya unsub email
    }

    public function test_campaign_audience_options_and_channels(): void
    {
        $campaign = new PromoCampaign([
            'channels' => [PromoCampaign::CHANNEL_EMAIL, PromoCampaign::CHANNEL_IN_APP],
            'target_audience' => PromoCampaign::AUDIENCE_ALL,
        ]);

        $this->assertTrue($campaign->hasChannel(PromoCampaign::CHANNEL_EMAIL));
        $this->assertTrue($campaign->hasChannel(PromoCampaign::CHANNEL_IN_APP));
        $this->assertFalse($campaign->hasChannel(PromoCampaign::CHANNEL_BANNER));

        $options = PromoCampaign::audienceOptions();
        $this->assertArrayHasKey(PromoCampaign::AUDIENCE_ALL, $options);
        $this->assertArrayHasKey(PromoCampaign::AUDIENCE_SUBSCRIBERS, $options);
        $this->assertArrayHasKey(PromoCampaign::AUDIENCE_SPECIFIC, $options);
    }
}
