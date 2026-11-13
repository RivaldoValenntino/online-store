<?php

namespace App\Services;

use App\Models\Address;
use App\Models\StoreShippingLocation;
use Exception;
use Illuminate\Support\Collection;

class ShippingService
{
    public function __construct(
        private readonly BiteshipSettings $settings
    ) {}

    public function rates(Address $address, Collection $items, int $totalWeight, ?StoreShippingLocation $customOrigin = null): array
    {
        $origin = $customOrigin ?: StoreShippingLocation::getDefault();

        $originAreaId = $origin?->biteship_area_id ?: $this->settings->get('origin_area_id');
        $originPostalCode = $origin?->postal_code ?: $this->settings->get('origin_postal_code');

        if (! $originAreaId && ! $originPostalCode) {
            throw new Exception('Alamat asal pengiriman toko belum dikonfigurasi. Hubungi admin.');
        }

        $payload = [
            'origin_area_id' => $originAreaId,
            'destination_area_id' => $address->biteship_area_id,
            'couriers' => $this->settings->get('default_couriers', 'jne,jnt,sicepat,anteraja'),
            'items' => $items->map(function ($item) {
                $product = $item->product ?? (is_array($item) ? ($item['product'] ?? null) : null);
                $variant = $item->variant ?? (is_array($item) ? ($item['variant'] ?? null) : null);

                $weight = $variant?->weight_gram ?? ($product?->weight_gram ?? (is_array($item) ? ($item['weight_gram'] ?? 0) : ($item->weight_gram ?? 0)));
                $price = (int) round((float) ($variant?->price ?? $product?->price ?? (is_array($item) ? ($item['price'] ?? 0) : ($item->price ?? 0))));

                return [
                    'name' => $product?->name ?? (is_array($item) ? ($item['product_name'] ?? 'Produk') : ($item->product_name ?? 'Produk')),
                    'description' => $variant?->name ?: ($product?->name ?? 'Produk'),
                    'value' => $price,
                    'quantity' => (int) ($item->qty ?? (is_array($item) ? ($item['qty'] ?? 1) : 1)),
                    'weight' => (int) max(1, $weight),
                ];
            })->values()->all(),
        ];

        if ($originPostalCode) {
            $payload['origin_postal_code'] = (int) $originPostalCode;
        }

        if ($address->biteship_postal_code || $address->postal_code) {
            $payload['destination_postal_code'] = (int) ($address->biteship_postal_code ?: $address->postal_code);
        }

        if ($origin && filled($origin->latitude) && filled($origin->longitude)) {
            $payload['origin_latitude'] = (float) $origin->latitude;
            $payload['origin_longitude'] = (float) $origin->longitude;
        }

        if (filled($address->latitude) && filled($address->longitude)) {
            $payload['destination_latitude'] = (float) $address->latitude;
            $payload['destination_longitude'] = (float) $address->longitude;
        }

        return app(BiteshipService::class)->rates($payload);
    }
}
