<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class BiteshipWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        logger()->info('BITESHIP WEBHOOK HIT', [
            'payload' => $request->all(),
        ]);

        $payload = $request->all();

        $event = $payload['event'] ?? $payload['type'] ?? null;

        $biteshipOrderId =
            $payload['order_id']
            ?? $payload['id']
            ?? data_get($payload, 'data.id')
            ?? data_get($payload, 'data.order_id')
            ?? data_get($payload, 'order.id');

        $status =
            $payload['status']
            ?? data_get($payload, 'data.status')
            ?? data_get($payload, 'order.status');

        $waybillId =
            $payload['courier_waybill_id']
            ?? $payload['waybill_id']
            ?? data_get($payload, 'data.courier_waybill_id')
            ?? data_get($payload, 'data.waybill_id')
            ?? data_get($payload, 'order.courier_waybill_id');

        $trackingId =
            $payload['courier_tracking_id']
            ?? $payload['tracking_id']
            ?? data_get($payload, 'data.courier_tracking_id')
            ?? data_get($payload, 'data.tracking_id')
            ?? data_get($payload, 'order.courier_tracking_id');

        $labelUrl =
            $payload['courier_link']
            ?? data_get($payload, 'data.courier_link')
            ?? data_get($payload, 'order.courier_link');

        if (! $biteshipOrderId) {
            logger()->warning('BITESHIP WEBHOOK ORDER ID EMPTY', [
                'event' => $event,
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Order ID empty'], 200);
        }

        $order = Order::query()
            ->where('biteship_order_id', $biteshipOrderId)
            ->first();

        if (! $order) {
            logger()->warning('BITESHIP ORDER NOT FOUND', [
                'biteship_order_id' => $biteshipOrderId,
                'event' => $event,
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Order not found'], 200);
        }

        $updates = [];

        if ($waybillId) {
            $updates['biteship_waybill_id'] = $waybillId;
        }

        if ($trackingId || $waybillId) {
            $updates['tracking_number'] = $trackingId ?: $waybillId;
        }

        if ($labelUrl) {
            $updates['shipping_label_url'] = $labelUrl;
        }

        if ($status) {
            $updates = array_merge($updates, $this->mapShippingStatus($status, $order));
        }

        if (! empty($updates)) {
            $order->update($updates);
        }

        return response()->json(['message' => 'OK']);
    }

    private function mapShippingStatus(string $biteshipStatus, Order $order): array
    {
        $status = strtolower($biteshipStatus);

        return match ($status) {
            // Sudah masuk Biteship / sedang proses pickup / belum benar-benar jalan
            'confirmed',
            'allocated',
            'scheduled',
            'picking_up',
            'courier_allocated',
            'on_process',
            'on_progress',
            'dropping_off' => [
                'status' => 'processing',
                'shipping_status' => 'processed',
            ],

            // Kurir belum ketemu, jangan masuk diproses penuh
            'courier_not_found' => [
                'status' => 'confirmed',
                'shipping_status' => 'waiting_shipment',
            ],

            // Paket sudah pickup / dalam perjalanan
            'pickup',
            'picked',
            'picked_up',
            'in_transit',
            'shipped' => [
                'status' => 'processing',
                'shipping_status' => 'shipped',
                'shipped_at' => $order->shipped_at ?: now(),
            ],

            // Paket sampai
            'delivered',
            'done',
            'completed' => [
                'status' => 'completed',
                'shipping_status' => 'delivered',
                'delivered_at' => $order->delivered_at ?: now(),
            ],

            // Pengiriman dibatalkan
            'cancelled',
            'canceled' => [
                'status' => 'cancelled',
                'shipping_status' => 'cancelled',
            ],

            // Pengiriman gagal
            'failed' => [
                'status' => 'cancelled',
                'shipping_status' => 'failed',
            ],

            // Retur / disposed
            'returned',
            'disposed' => [
                'status' => 'cancelled',
                'shipping_status' => 'returned',
            ],

            default => [
                'shipping_status' => $order->shipping_status,
            ],
        };
    }
}