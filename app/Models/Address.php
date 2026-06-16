<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
