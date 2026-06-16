<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BiteshipService
{
    private function client()
    {
        return Http::withToken(config('services.biteship.api_key'))
            ->acceptJson()
            ->asJson()
            ->baseUrl('https://api.biteship.com');
    }
    public function rates(array $payload): array
    {
        $response = $this->client()
            ->post('/v1/rates/couriers', $payload)
            ->throw()
            ->json();

        return collect($response['pricing'] ?? [])
            ->map(function (array $item) {
                return [
                    'key' => strtolower(($item['courier_code'] ?? '') . '-' . ($item['courier_service_code'] ?? '')),
                    'code' => $item['courier_code'] ?? '',
                    'name' => $item['courier_name'] ?? strtoupper($item['courier_code'] ?? ''),
                    'service' => $item['courier_service_code'] ?? '',
                    'service_name' => $item['courier_service_name'] ?? null,
                    'description' => $item['description'] ?? null,
                    'cost' => (int) ($item['price'] ?? 0),
                    'etd' => $item['duration'] ?? null,
                    'raw' => $item,
                ];
            })
            ->filter(fn(array $item) => filled($item['key']) && $item['cost'] > 0)
            ->values()
            ->all();
    }

    public function searchAreas(string $keyword): array
    {
        if (strlen(trim($keyword)) < 3) {
            return [];
        }

        $response = $this->client()
            ->get('/v1/maps/areas', [
                'countries' => 'ID',
                'input' => $keyword,
                'type' => 'single',
            ])
            ->throw()
            ->json();

        return collect($response['areas'] ?? [])
            ->map(function ($area) {
                return [
                    'id' => (string) ($area['id'] ?? ''),
                    'name' => (string) ($area['name'] ?? ''),
                    'country_name' => $area['country_name'] ?? null,
                    'country_code' => $area['country_code'] ?? null,
                    'administrative_division_level_1_name' => $area['administrative_division_level_1_name'] ?? null,
                    'administrative_division_level_2_name' => $area['administrative_division_level_2_name'] ?? null,
                    'administrative_division_level_3_name' => $area['administrative_division_level_3_name'] ?? null,
                    'postal_code' => $area['postal_code'] ?? null,
                    'raw' => $area,
                ];
            })
            ->filter(fn($area) => filled($area['id']))
            ->values()
            ->all();
    }

    public function areaLabel(array $area): string
    {
        return collect([
            $area['name'] ?? null,
            $area['administrative_division_level_3_name'] ?? null,
            $area['administrative_division_level_2_name'] ?? null,
            $area['administrative_division_level_1_name'] ?? null,
            $area['postal_code'] ?? null,
        ])->filter()->unique()->implode(', ');
    }
    public function createOrder(\App\Models\Order $order): array
    {
        $order->load(['items.product', 'items.variant', 'address']);

        $address = $order->address;

        if (! $address) {
            throw new \Exception('Alamat order tidak ditemukan.');
        }

        if (! $address->biteship_area_id && ! $address->biteship_postal_code && ! $address->postal_code) {
            throw new \Exception('Alamat belum punya area ID atau kode pos Biteship.');
        }

        $payload = [
            'origin_contact_name' => config('services.biteship.origin_contact_name'),
            'origin_contact_phone' => config('services.biteship.origin_contact_phone'),
            'origin_address' => config('services.biteship.origin_address'),
            'origin_area_id' => config('services.biteship.origin_area_id'),

            'destination_contact_name' => $order->recipient_name,
            'destination_contact_phone' => $order->recipient_phone,
            'destination_address' => $order->shipping_address,
            'destination_area_id' => $address->biteship_area_id,
            'destination_postal_code' => (int) ($address->biteship_postal_code ?: $address->postal_code),

            'courier_company' => $order->shipping_courier,
            'courier_type' => $order->shipping_service,

            'delivery_type' => 'now',
            'order_note' => $order->notes,

            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->product_name,
                    'description' => $item->variant_name ?: $item->product_name,
                    'value' => (int) $item->price,
                    'quantity' => (int) $item->qty,
                    'weight' => (int) $item->weight_gram,
                ];
            })->values()->all(),
        ];

        logger()->info('BITESHIP CREATE ORDER PAYLOAD', [
            'order_id' => $order->id,
            'payload' => $payload,
        ]);

        return $this->client()
            ->post('/v1/orders', $payload)
            ->throw()
            ->json();
    }
    public function cancelOrder(string $biteshipOrderId, ?string $reason = null): array
    {
        $payload = [];

        if ($reason) {
            $payload['reason'] = $reason;
        }

        return $this->client()
            ->post("/v1/orders/{$biteshipOrderId}/cancel", $payload)
            ->throw()
            ->json();
    }
}
