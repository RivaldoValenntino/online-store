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
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomerVerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPasswordNotification($token));
    }
}
