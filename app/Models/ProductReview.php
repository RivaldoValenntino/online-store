<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductReview extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'product_id',
        'customer_id',
        'order_id',
        'order_item_id',
        'rating',
        'review',
        'status',
        'is_verified_purchase',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductReview $review) {
            app(\App\Services\ProductReviewService::class)->recalculateProductRating($review->product_id);
        });

        static::deleted(function (ProductReview $review) {
            app(\App\Services\ProductReviewService::class)->recalculateProductRating($review->product_id);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductReviewImage::class)->orderBy('sort_order');
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ProductReviewReply::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeWithRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    /**
     * Display a friendly masked name for customer privacy (e.g. "Budi Santoso" -> "B***o").
     */
    public function getMaskedCustomerNameAttribute(): string
    {
        $name = trim($this->customer?->name ?? 'Pelanggan');

        if (mb_strlen($name) <= 2) {
            return $name;
        }

        $firstChar = mb_substr($name, 0, 1);
        $lastChar = mb_substr($name, -1);

        return $firstChar . '***' . $lastChar;
    }

    public function getCustomerMaskedNameAttribute(): string
    {
        return $this->getMaskedCustomerNameAttribute();
    }
}
