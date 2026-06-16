<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (ProductVariant $variant) {
            if ($variant->isDirty('image')) {
                $oldImage = $variant->getOriginal('image');

                if ($oldImage && $oldImage !== $variant->image) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
        });

        static::deleted(function (ProductVariant $variant) {
            if ($variant->image) {
                Storage::disk('public')->delete($variant->image);
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues()
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_values',
            'product_variant_id',
            'product_option_value_id'
        );
    }
}
