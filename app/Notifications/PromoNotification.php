<?php

namespace App\Notifications;

use App\Models\PromoCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PromoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PromoCampaign $campaign
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'promo',
            'campaign_id' => $this->campaign->id,
            'title' => $this->campaign->title,
            'body' => $this->campaign->content ? mb_substr(strip_tags($this->campaign->content), 0, 160) : 'Ada promo spesial untuk Anda!',
            'voucher_code' => $this->campaign->voucher?->code,
            'action_url' => $this->campaign->action_url ?: route('store.promos'),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
