<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_review_id',
        'user_id',
        'reply',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the official store brand display name.
     */
    public function getStoreDisplayNameAttribute(): string
    {
        return (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));
    }
}
