<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // protected $fillable = [
    //     'customer_id',
    //     'address_id',
    //     'order_number',
    //     'subtotal',
    //     'shipping_cost',
    //     'discount',
    //     'grand_total',
    //     'status',
    //     'payment_status',
    //     'shipping_status',
    //     'recipient_name',
    //     'recipient_phone',
    //     'shipping_address',
    // ];
    protected $guarded = ['id'];
    protected $casts = [
        'payment_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'stock_restored_at' => 'datetime',
        'shipment_created_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'stock_reserved_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
        'stock_released_at' => 'datetime',
        'stock_admin_review_required' => 'boolean',
        'origin_latitude' => 'float',
        'origin_longitude' => 'float',
    ];
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function voucherUsage()
    {
        return $this->hasOne(VoucherUsage::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status, ['completed', 'delivered'], true)
            || $this->shipping_status === 'delivered';
    }
}
