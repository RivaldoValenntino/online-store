<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RajaOngkirService
{
    public function domesticCost(int $destinationId, int $weightGram): array
    {
        $payload = [
            'origin' => (int) config('services.rajaongkir.origin_district_id'),
            'destination' => $destinationId,
            'weight' => max($weightGram, 1),
            'courier' => config('services.rajaongkir.courier'),
            'price' => 'lowest',
        ];

        $response = Http::asForm()
            ->withHeaders([
                'key' => config('services.rajaongkir.api_key'),
            ])
            ->post('https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost', $payload);

        Log::info('RAJAONGKIR DOMESTIC COST', [
            'payload' => $payload,
            'status' => $response->status(),
            'body' => $response->json(),
            'raw' => $response->body(),
        ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('data') ?? [])
            ->map(function ($item) {
                return [
                    'key' => strtolower(($item['code'] ?? '') . '|' . ($item['service'] ?? '')),
                    'name' => $item['name'] ?? strtoupper($item['code'] ?? ''),
                    'code' => $item['code'] ?? '',
                    'service' => $item['service'] ?? '',
                    'description' => $item['description'] ?? '',
                    'cost' => (float) ($item['cost'] ?? 0),
                    'etd' => $item['etd'] ?? '',
                ];
            })
            ->filter(fn($item) => $item['code'] && $item['service'])
            ->values()
            ->all();
    }

    public function searchDomesticDestination(string $keyword): array
    {
        $response = Http::withHeaders([
            'key' => config('services.rajaongkir.api_key'),
        ])->get('https://rajaongkir.komerce.id/api/v1/destination/domestic-destination', [
            'search' => $keyword,
            'limit' => 10,
            'offset' => 0,
        ]);

        Log::info('RAJAONGKIR SEARCH DESTINATION', [
            'keyword' => $keyword,
            'status' => $response->status(),
            'body' => $response->json(),
            'raw' => $response->body(),
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json('data') ?? [];
    }
}
