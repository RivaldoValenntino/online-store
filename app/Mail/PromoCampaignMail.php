<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\PromoCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromoCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PromoCampaign $campaign,
        public Customer $customer
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name', (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos')))
            ),
            subject: $this->campaign->subject ?: $this->campaign->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaigns.promo',
            with: [
                'campaign' => $this->campaign,
                'customer' => $this->customer,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
