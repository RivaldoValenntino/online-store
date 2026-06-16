<?php

namespace App\Services;

use App\Models\Order;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnapTransaction(Order $order, ?string $midtransOrderId = null): object
    {
        $order->load(['items', 'customer']);

        $itemDetails = $order->items
            ->map(function ($item) {
                return [
                    'id' => (string) $item->id,
                    'price' => (int) $item->price,
                    'quantity' => (int) $item->qty,
                    'name' => mb_substr($item->variant_name ?: $item->product_name, 0, 50),
                ];
            })
            ->values()
            ->all();

        if ($order->shipping_cost > 0) {
            $itemDetails[] = [
                'id' => 'SHIPPING',
                'price' => (int) $order->shipping_cost,
                'quantity' => 1,
                'name' => mb_substr(
                    'Ongkir ' . strtoupper($order->shipping_courier) . ' ' . $order->shipping_service,
                    0,
                    50
                ),
            ];
        }

        if ($order->discount > 0) {
            $itemDetails[] = [
                'id' => 'DISCOUNT',
                'price' => -1 * (int) $order->discount,
                'quantity' => 1,
                'name' => 'Diskon',
            ];
        }

        // $durationMinutes = (int) max(
        //     1,
        //     floor(now()->diffInMinutes($order->payment_expires_at, false))
        // );
        $durationMinutes = (int) max(
            1,
            ceil(now()->diffInMinutes($order->payment_expires_at, false))
        );

        return Snap::createTransaction([
            'transaction_details' => [
                'order_id' => $midtransOrderId ?: $order->order_number,
                'gross_amount' => (int) $order->grand_total,
            ],

            'customer_details' => [
                'first_name' => $order->recipient_name,
                'email' => $order->customer?->email,
                'phone' => $order->recipient_phone,
                'shipping_address' => [
                    'first_name' => $order->recipient_name,
                    'phone' => $order->recipient_phone,
                    'address' => $order->shipping_address,
                    'country_code' => 'IDN',
                ],
            ],

            'item_details' => $itemDetails,

            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => $durationMinutes,
            ],

            'callbacks' => [
                'finish' => route('payment.midtrans.return'),
            ],
        ]);
    }
}
