<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (filled($product->name)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::deleting(function (Product $product) {
            $product->images()->get()->each(function ($image) {
                if ($image->image) {
                    Storage::disk('public')->delete($image->image);
                }
            });

            $product->variants()->get()->each(function ($variant) {
                if ($variant->image) {
                    Storage::disk('public')->delete($variant->image);
                }
            });
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', 1);
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistedBy()
    {
        return $this->belongsToMany(Customer::class, 'wishlists')->withTimestamps();
    }

    public function isWishlistedBy(?Customer $customer): bool
    {
        if (! $customer) {
            return false;
        }

        return $this->wishlists()->where('customer_id', $customer->id)->exists();
    }
}
