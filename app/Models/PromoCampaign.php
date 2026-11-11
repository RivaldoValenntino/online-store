<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCampaign extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'channels' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public const CHANNEL_BANNER = 'banner';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_IN_APP = 'in_app';

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_PURCHASED = 'purchased';
    public const AUDIENCE_NON_PURCHASED = 'non_purchased';
    public const AUDIENCE_NEW = 'new_users';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function scopePendingDispatch(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            });
    }

    public function hasChannel(string $channel): bool
    {
        return is_array($this->channels) && in_array($channel, $this->channels, true);
    }
}
