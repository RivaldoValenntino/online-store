<?php

namespace App\Jobs;

use App\Mail\PromoCampaignMail;
use App\Models\Customer;
use App\Models\PromoCampaign;
use App\Notifications\PromoNotification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DispatchPromoCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public int $campaignId
    ) {}

    public function handle(): void
    {
        $campaign = PromoCampaign::find($this->campaignId);

        if (!$campaign || in_array($campaign->status, [PromoCampaign::STATUS_COMPLETED, PromoCampaign::STATUS_SENDING])) {
            return;
        }

        $campaign->update([
            'status' => PromoCampaign::STATUS_SENDING,
        ]);

        try {
            $query = Customer::query()->where('is_active', true);

            switch ($campaign->target_audience) {
                case PromoCampaign::AUDIENCE_PURCHASED:
                    $query->whereHas('orders', fn($q) => $q->where('payment_status', 'paid'));
                    break;

                case PromoCampaign::AUDIENCE_NON_PURCHASED:
                    $query->whereDoesntHave('orders', fn($q) => $q->where('payment_status', 'paid'));
                    break;

                case PromoCampaign::AUDIENCE_NEW:
                    $query->whereDoesntHave('orders');
                    break;
            }

            $sentCount = 0;
            $failedCount = 0;

            $query->chunkById(100, function ($customers) use ($campaign, &$sentCount, &$failedCount) {
                foreach ($customers as $customer) {
                    try {
                        // Email Channel
                        if ($campaign->hasChannel(PromoCampaign::CHANNEL_EMAIL) && !$customer->unsubscribed_from_promos) {
                            Mail::to($customer->email)->queue(new PromoCampaignMail($campaign, $customer));
                        }

                        // In-App Notification Channel
                        if ($campaign->hasChannel(PromoCampaign::CHANNEL_IN_APP)) {
                            // Check idempotency: avoid duplicate notification for same campaign
                            $alreadyNotified = $customer->notifications()
                                ->where('data->campaign_id', $campaign->id)
                                ->exists();

                            if (!$alreadyNotified) {
                                $customer->notify(new PromoNotification($campaign));
                            }
                        }

                        $sentCount++;
                    } catch (Exception $e) {
                        $failedCount++;
                        Log::error("Gagal mengirim campaign #{$campaign->id} ke customer #{$customer->id}: " . $e->getMessage());
                    }
                }
            });

            $campaign->update([
                'status' => PromoCampaign::STATUS_COMPLETED,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'sent_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error("Fatal error pada dispatch campaign #{$campaign->id}: " . $e->getMessage());
            $campaign->update([
                'status' => PromoCampaign::STATUS_FAILED,
            ]);
        }
    }
}
