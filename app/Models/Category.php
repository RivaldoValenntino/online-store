<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $guarded = ['id'];
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    protected static function booted(): void
    {
        static::updating(function (Category $category) {
            if ($category->isDirty('image')) {
                $oldImage = $category->getOriginal('image');

                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
        });

        static::deleted(function (Category $category) {
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
        });
    }
}
