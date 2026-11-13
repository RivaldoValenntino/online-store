<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StoreShippingLocation extends Model
{
    use HasFactory;

    protected $table = 'store_shipping_locations';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $location) {
            // Default location must be active
            if ($location->is_default) {
                $location->is_active = true;
            }
        });

        static::saved(function (self $location) {
            if ($location->is_default) {
                // Ensure only one default location exists
                static::query()
                    ->where('id', '!=', $location->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::deleting(function (self $location) {
            if ($location->is_default && static::query()->where('is_active', true)->count() > 1) {
                throw new Exception('Lokasi pengiriman default tidak dapat dihapus sebelum menentukan lokasi default baru.');
            }
        });
    }

    /**
     * Scope active locations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the authoritative active default store shipping location.
     */
    public static function getDefault(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?: static::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->latest()
                ->first();
    }

    /**
     * Mark this location as the active default location.
     */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->update([
                'is_default' => true,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Get formatted full address with postal code.
     */
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address,
            $this->subdistrict,
            $this->district,
            $this->city,
            $this->province,
            $this->postal_code,
        ])->filter()->implode(', ');
    }
}
