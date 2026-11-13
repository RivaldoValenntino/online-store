<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewImage;
use App\Models\ProductReviewReply;
use App\Models\User;
use App\Notifications\ProductReviewReplyNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    /**
     * Check if a customer is eligible to review a given order item.
     */
    public function canCustomerReviewOrderItem(Customer $customer, OrderItem $orderItem): bool
    {
        $order = $orderItem->order;

        if (! $order || (int) $order->customer_id !== (int) $customer->id) {
            return false;
        }

        // Must be paid & completed / delivered
        $isCompleted = in_array($order->status, ['completed', 'delivered'], true)
            || $order->shipping_status === 'delivered';

        if (! $isCompleted || $order->payment_status !== 'paid') {
            return false;
        }

        // Must not already have a review
        return ! ProductReview::where('order_item_id', $orderItem->id)->exists();
    }

    /**
     * Create a verified review for a purchased order item.
     *
     * @param OrderItem|int $orderItem
     * @param array{rating: int, review: string} $data
     * @param array<int, UploadedFile> $uploadedFiles
     */
    public function createReview(Customer $customer, OrderItem|int $orderItem, array $data, array $uploadedFiles = []): ProductReview
    {
        if (is_int($orderItem)) {
            $foundItem = OrderItem::find($orderItem);
            if (! $foundItem) {
                throw new AuthorizationException('Item pesanan tidak ditemukan.');
            }
            $orderItem = $foundItem;
        }

        $orderItem->loadMissing('order');

        if (! $this->canCustomerReviewOrderItem($customer, $orderItem)) {
            throw new AuthorizationException('Anda tidak memenuhi syarat untuk mengulas produk ini.');
        }

        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Rating bintang harus antara 1 sampai 5.',
            ]);
        }

        $reviewText = trim((string) ($data['review'] ?? ''));
        if (mb_strlen($reviewText) < 3) {
            throw ValidationException::withMessages([
                'review' => 'Ulasan minimal berisi 3 karakter.',
            ]);
        }

        if (count($uploadedFiles) > 5) {
            throw ValidationException::withMessages([
                'photos' => 'Maksimal 5 foto per ulasan.',
            ]);
        }

        // Status is always approved by default for verified purchase, or custom if internal
        // Client input for 'status' or 'is_verified_purchase' is strictly ignored.
        $initialStatus = $data['_internal_status'] ?? ProductReview::STATUS_APPROVED;

        return DB::transaction(function () use ($customer, $orderItem, $rating, $reviewText, $uploadedFiles, $initialStatus) {
            $review = ProductReview::create([
                'product_id' => $orderItem->product_id,
                'customer_id' => $customer->id,
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
                'rating' => $rating,
                'review' => $reviewText,
                'status' => $initialStatus,
                'is_verified_purchase' => true,
            ]);

            $this->storeReviewImages($review, $uploadedFiles);

            $this->recalculateProductRating($review->product_id);

            return $review;
        });
    }

    /**
     * Update an existing review belonging to the customer.
     *
     * @param array{rating: int, review: string} $data
     * @param array<int, UploadedFile> $newUploadedFiles
     * @param array<int, int> $deletedImageIds
     */
    public function updateReview(
        Customer $customer,
        ProductReview $review,
        array $data,
        array $newUploadedFiles = [],
        array $deletedImageIds = []
    ): ProductReview {
        if ((int) $review->customer_id !== (int) $customer->id) {
            throw new AuthorizationException('Anda tidak berhak mengedit ulasan ini.');
        }

        $rating = (int) ($data['rating'] ?? $review->rating);
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Rating bintang harus antara 1 sampai 5.',
            ]);
        }

        $reviewText = trim((string) ($data['review'] ?? $review->review));
        if (mb_strlen($reviewText) < 3) {
            throw ValidationException::withMessages([
                'review' => 'Ulasan minimal berisi 3 karakter.',
            ]);
        }

        return DB::transaction(function () use ($review, $rating, $reviewText, $newUploadedFiles, $deletedImageIds) {
            // Delete specified existing images
            if (! empty($deletedImageIds)) {
                $imagesToDelete = $review->images()->whereIn('id', $deletedImageIds)->get();
                foreach ($imagesToDelete as $img) {
                    $img->delete();
                }
            }

            // Check total image count after deletion
            $currentCount = $review->images()->count();
            if ($currentCount + count($newUploadedFiles) > 5) {
                throw ValidationException::withMessages([
                    'photos' => 'Total foto untuk ulasan ini tidak boleh melebihi 5 foto.',
                ]);
            }

            // Customer cannot change status or is_verified_purchase!
            $review->update([
                'rating' => $rating,
                'review' => $reviewText,
            ]);

            $this->storeReviewImages($review, $newUploadedFiles, $currentCount);

            $this->recalculateProductRating($review->product_id);

            return $review->fresh(['images', 'reply']);
        });
    }

    /**
     * Delete an existing review belonging to the customer.
     */
    public function deleteReview(Customer $customer, ProductReview $review): bool
    {
        if ((int) $review->customer_id !== (int) $customer->id) {
            throw new AuthorizationException('Anda tidak berhak menghapus ulasan ini.');
        }

        return DB::transaction(function () use ($review) {
            $productId = $review->product_id;

            // Delete associated images
            foreach ($review->images as $image) {
                $image->delete();
            }

            $review->delete();

            $this->recalculateProductRating($productId);

            return true;
        });
    }

    /**
     * Store uploaded review images onto the public storage disk.
     */
    protected function storeReviewImages(ProductReview $review, array $uploadedFiles, int $startIndex = 0): void
    {
        $order = $startIndex;

        foreach ($uploadedFiles as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            // Validate image mime
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
            if (! in_array($file->getMimeType(), $allowedMimes, true)) {
                continue;
            }

            $path = $file->store("reviews/{$review->id}", 'public');

            ProductReviewImage::create([
                'product_review_id' => $review->id,
                'image_path' => $path,
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * Recalculate average rating and review count for a product, updating the database record.
     */
    public function recalculateProductRating(Product|int $product): void
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $approvedQuery = ProductReview::query()
            ->where('product_id', $productId)
            ->where('status', ProductReview::STATUS_APPROVED);

        $count = (int) $approvedQuery->count();
        $avg = $count > 0 ? (float) round((float) $approvedQuery->avg('rating'), 2) : 0.00;

        Product::query()->where('id', $productId)->update([
            'rating_avg' => $avg,
            'reviews_count' => $count,
        ]);
    }

    /**
     * Calculate star rating distribution (1 to 5) for a product.
     *
     * @return array{average: float, total: int, distribution: array<int, array{count: int, percentage: int}>}
     */
    public function getRatingBreakdown(Product|int $product): array
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $reviews = ProductReview::query()
            ->where('product_id', $productId)
            ->where('status', ProductReview::STATUS_APPROVED)
            ->get(['rating']);

        $total = $reviews->count();
        $avg = $total > 0 ? (float) round((float) $reviews->avg('rating'), 1) : 0.0;

        $counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($reviews as $rev) {
            $star = (int) $rev->rating;
            if (isset($counts[$star])) {
                $counts[$star]++;
            }
        }

        $distribution = [];
        for ($s = 5; $s >= 1; $s--) {
            $c = $counts[$s];
            $pct = $total > 0 ? (int) round(($c / $total) * 100) : 0;
            $distribution[$s] = [
                'count' => $c,
                'percentage' => $pct,
            ];
        }

        return [
            'average' => $avg,
            'total' => $total,
            'distribution' => $distribution,
        ];
    }

    /**
     * Admin action to approve a review.
     */
    public function approveReview(ProductReview $review): void
    {
        $review->update(['status' => ProductReview::STATUS_APPROVED]);
        $this->recalculateProductRating($review->product_id);
    }

    /**
     * Admin action to reject/hide a review.
     */
    public function rejectReview(ProductReview $review): void
    {
        $review->update(['status' => ProductReview::STATUS_REJECTED]);
        $this->recalculateProductRating($review->product_id);
    }

    /**
     * Admin action to reply to a review.
     * Supports both ($review, $text, $admin) and ($admin, $review, $text).
     */
    public function saveAdminReply(ProductReview|User $first, string|ProductReview $second, string|User|null $third = null): ProductReviewReply
    {
        if ($first instanceof User && $second instanceof ProductReview) {
            $admin = $first;
            $review = $second;
            $replyText = (string) $third;
        } else {
            /** @var ProductReview $review */
            $review = $first;
            $replyText = (string) $second;
            $admin = $third instanceof User ? $third : null;
        }

        $reply = ProductReviewReply::updateOrCreate(
            ['product_review_id' => $review->id],
            [
                'user_id' => $admin?->id,
                'reply' => trim($replyText),
            ]
        );

        // Notify customer via in-app database notification
        try {
            $customer = $review->customer;
            if ($customer) {
                $customer->notify(new ProductReviewReplyNotification($review));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed sending review reply notification: ' . $e->getMessage());
        }

        return $reply;
    }
}
