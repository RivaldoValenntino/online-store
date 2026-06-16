<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantValue extends Model
{
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function optionValue()
    {
        return $this->belongsTo(ProductOptionValue::class, 'product_option_value_id');
    }
}
