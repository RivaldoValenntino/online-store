<?php

namespace App\Models;

use App\Notifications\CustomerResetPasswordNotification;
use App\Notifications\CustomerVerifyEmailNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Customer extends Authenticatable implements MustVerifyEmailContract, CanResetPasswordContract
{
    use Notifiable;
    use CanResetPassword;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unsubscribed_from_promos' => 'boolean',
        'email_marketing' => 'boolean',
        'in_app_marketing' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if ($customer->isDirty('password') && filled($customer->password)) {
                if (! Hash::needsRehash($customer->password)) {
                    return;
                }

                $customer->password = Hash::make($customer->password);
            }
        });
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function voucherUsages()
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomerVerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPasswordNotification($token));
    }

    public function canReceiveEmailMarketing(): bool
    {
        return $this->is_active
            && (is_null($this->email_marketing) || $this->email_marketing)
            && !$this->unsubscribed_from_promos;
    }

    public function canReceiveInAppMarketing(): bool
    {
        return $this->is_active
            && (is_null($this->in_app_marketing) || $this->in_app_marketing);
    }

    public function scopeEmailMarketingSubscribers($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('email_marketing')->orWhere('email_marketing', true);
            })
            ->where('unsubscribed_from_promos', false);
    }

    public function scopeInAppMarketingEnabled($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('in_app_marketing')->orWhere('in_app_marketing', true);
            });
    }
}
