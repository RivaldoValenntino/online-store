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

                case PromoCampaign::AUDIENCE_SUBSCRIBERS:
                    $query->emailMarketingSubscribers();
                    break;

                case PromoCampaign::AUDIENCE_SPECIFIC:
                    $targetIds = array_filter((array) ($campaign->target_customer_ids ?? []));
                    $query->whereIn('id', $targetIds);
                    break;

                case PromoCampaign::AUDIENCE_ALL:
                default:
                    // Ambil semua pengguna terdaftar yang aktif
                    break;
            }

            // Filter preferensi kanal: Hanya ambil user yang mengaktifkan setidaknya salah satu kanal yang dipilih pada campaign
            $hasEmailChannel = $campaign->hasChannel(PromoCampaign::CHANNEL_EMAIL);
            $hasInAppChannel = $campaign->hasChannel(PromoCampaign::CHANNEL_IN_APP);

            if ($hasEmailChannel && !$hasInAppChannel) {
                $query->emailMarketingSubscribers();
            } elseif (!$hasEmailChannel && $hasInAppChannel) {
                $query->inAppMarketingEnabled();
            } elseif ($hasEmailChannel && $hasInAppChannel) {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where(function ($sq) {
                            $sq->whereNull('email_marketing')->orWhere('email_marketing', true);
                        })->where('unsubscribed_from_promos', false);
                    })->orWhere(function ($sub) {
                        $sub->whereNull('in_app_marketing')->orWhere('in_app_marketing', true);
                    });
                });
            }

            $sentCount = 0;
            $failedCount = 0;

            $query->chunkById(100, function ($customers) use ($campaign, &$sentCount, &$failedCount) {
                foreach ($customers as $customer) {
                    try {
                        $sentToCustomer = false;

                        // Email Channel
                        if ($campaign->hasChannel(PromoCampaign::CHANNEL_EMAIL) && $customer->canReceiveEmailMarketing()) {
                            Mail::to($customer->email)->queue(new PromoCampaignMail($campaign, $customer));
                            $sentToCustomer = true;
                        }

                        // In-App Notification Channel
                        if ($campaign->hasChannel(PromoCampaign::CHANNEL_IN_APP) && $customer->canReceiveInAppMarketing()) {
                            // Check idempotency: avoid duplicate notification for same campaign
                            $alreadyNotified = $customer->notifications()
                                ->where('data->campaign_id', $campaign->id)
                                ->exists();

                            if (!$alreadyNotified) {
                                $customer->notify(new PromoNotification($campaign));
                                $sentToCustomer = true;
                            }
                        }

                        if ($sentToCustomer) {
                            $sentCount++;
                        }
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
