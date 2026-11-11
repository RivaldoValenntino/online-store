<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Support\Collection;

class ShippingService
{
    public function __construct(
        private readonly BiteshipSettings $settings
    ) {}

    public function rates(Address $address, Collection $items, int $totalWeight): array
    {
        return app(BiteshipService::class)->rates([
            'origin_area_id' => $this->settings->get('origin_area_id'),
            'destination_area_id' => $address->biteship_area_id,
            'couriers' => $this->settings->get('default_couriers', 'jne,jnt,sicepat,anteraja'),
            'items' => $items->map(function ($item) {
                $product = $item->product;
                $variant = $item->variant;

                $weight = $variant?->weight_gram ?? ($product?->weight_gram ?? 0);

                return [
                    'name' => $product?->name ?? 'Produk',
                    'description' => $variant?->name ?: ($product?->name ?? 'Produk'),
                    'value' => (int) $item->price,
                    'quantity' => (int) $item->qty,
                    'weight' => (int) $weight,
                ];
            })->values()->all(),
        ]);
    }
}
