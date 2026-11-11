<?php

namespace App\Services;

use App\Jobs\DispatchPromoCampaignJob;
use App\Models\Customer;
use App\Models\PromoCampaign;

class PromoCampaignService
{
    public function dispatchNow(PromoCampaign $campaign): void
    {
        DispatchPromoCampaignJob::dispatch($campaign->id);
    }

    public function renderEmailPreview(PromoCampaign $campaign, ?Customer $customer = null): string
    {
        $previewCustomer = $customer ?? new Customer([
            'id' => 9999,
            'name' => 'Pelanggan Setia Sami Raos',
            'email' => 'pelanggan@example.com',
        ]);

        return view('emails.campaigns.promo', [
            'campaign' => $campaign,
            'customer' => $previewCustomer,
        ])->render();
    }
}
