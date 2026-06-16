<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = ['id'];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::updating(function (ProductImage $productImage) {
            if ($productImage->isDirty('image')) {
                $oldImage = $productImage->getOriginal('image');

                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
        });

        static::deleted(function (ProductImage $productImage) {
            if ($productImage->image && Storage::disk('public')->exists($productImage->image)) {
                Storage::disk('public')->delete($productImage->image);
            }
        });
    }
}
