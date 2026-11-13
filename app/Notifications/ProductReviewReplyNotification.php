<?php

namespace App\Notifications;

use App\Models\ProductReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductReviewReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ProductReview $review
    ) {
        $this->review->loadMissing(['product', 'reply']);
    }

    /**
     * In-app database notification only (transactional, non-marketing).
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $storeName = (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));
        $product = $this->review->product;
        $productName = $product?->name ?? 'Produk';
        $productSlug = $product?->slug ?? '';

        return [
            'type' => 'review_reply',
            'review_id' => $this->review->id,
            'product_id' => $this->review->product_id,
            'product_name' => $productName,
            'title' => "Toko Membalas Ulasan Anda",
            'message' => "{$storeName} telah membalas ulasan Anda untuk produk \"{$productName}\".",
            'icon' => 'chat-bubble-left-right',
            'color' => 'primary',
            'url' => $productSlug ? route('store.product.detail', $productSlug) : route('store.home'),
        ];
    }
}
