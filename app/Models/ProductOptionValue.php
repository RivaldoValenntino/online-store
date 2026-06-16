<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOptionValue extends Model
{
    protected $guarded = ['id'];

    public function option()
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function variants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_values',
            'product_option_value_id',
            'product_variant_id'
        );
    }
}
