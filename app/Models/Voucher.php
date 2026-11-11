<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'float',
        'min_spend' => 'float',
        'max_discount' => 'float',
        'total_quota' => 'integer',
        'total_used' => 'integer',
        'user_limit' => 'integer',
        'transaction_limit' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'eligible_category_ids' => 'array',
        'eligible_product_ids' => 'array',
    ];

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FREE_SHIPPING = 'free_shipping';
    public const TYPE_SHIPPING_DISCOUNT = 'shipping_discount';

    public static function types(): array
    {
        return [
            self::TYPE_PERCENTAGE => 'Persentase Diskon (%)',
            self::TYPE_FIXED => 'Potongan Tetap (Nominal)',
            self::TYPE_FREE_SHIPPING => 'Gratis Ongkir',
            self::TYPE_SHIPPING_DISCOUNT => 'Potongan Ongkir',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(PromoCampaign::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    public function scopeValidForUse(Builder $query): Builder
    {
        return $query->active()->where(function ($q) {
            $q->whereNull('total_quota')->orWhereColumn('total_used', '<', 'total_quota');
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isStarted(): bool
    {
        return !$this->starts_at || $this->starts_at->isPast();
    }

    public function hasQuota(): bool
    {
        return is_null($this->total_quota) || $this->total_used < $this->total_quota;
    }

    public function formattedDiscount(): string
    {
        return match ($this->type) {
            self::TYPE_PERCENTAGE => rtrim(rtrim(number_format($this->amount, 2), '0'), '.') . '%',
            self::TYPE_FIXED => 'Rp ' . number_format($this->amount, 0, ',', '.'),
            self::TYPE_FREE_SHIPPING => 'Gratis Ongkir',
            self::TYPE_SHIPPING_DISCOUNT => 'Potongan Ongkir Rp ' . number_format($this->amount, 0, ',', '.'),
            default => (string) $this->amount,
        };
    }
}
