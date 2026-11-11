<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BiteshipSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiteshipWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        logger()->info('BITESHIP WEBHOOK HIT', [
            'payload' => $request->all(),
            'header_key' => $this->getWebhookHeaderKey(),
            'signature_received' => filled($this->getIncomingSignature($request)),
        ]);

        if (! $this->validateSignature($request)) {
            logger()->warning('BITESHIP WEBHOOK REJECTED: Invalid or missing signature header', [
                'expected_header' => $this->getWebhookHeaderKey(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid or missing webhook signature.',
            ], 401);
        }

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

    /**
     * Validasi signature header webhook dari Biteship.
     */
    private function validateSignature(Request $request): bool
    {
        $expectedSecret = $this->getWebhookSecret();

        // Jika secret belum diset di server
        if (blank($expectedSecret)) {
            if (app()->isProduction()) {
                logger()->error('BITESHIP WEBHOOK REJECTED: Webhook secret is not configured in production.');
                return false;
            }

            logger()->warning('BITESHIP WEBHOOK WARNING: BITESHIP_WEBHOOK_SECRET is not configured.');
            return true;
        }

        $incomingSignature = $this->getIncomingSignature($request);

        if (blank($incomingSignature)) {
            return false;
        }

        // 1. Validasi token secret langsung (standard Biteship Dashboard Header Secret)
        if (hash_equals((string) $expectedSecret, (string) $incomingSignature)) {
            return true;
        }

        // 2. Validasi format Bearer token jika header dikirim dengan format "Bearer <secret>"
        if (str_starts_with((string) $incomingSignature, 'Bearer ')) {
            $token = substr((string) $incomingSignature, 7);
            if (hash_equals((string) $expectedSecret, $token)) {
                return true;
            }
        }

        // 3. Validasi HMAC-SHA256 jika signature berupa hash payload
        $rawContent = $request->getContent();
        if (filled($rawContent)) {
            $calculatedHmac = hash_hmac('sha256', $rawContent, (string) $expectedSecret);
            if (hash_equals($calculatedHmac, (string) $incomingSignature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nama header yang digunakan untuk signature webhook.
     */
    private function getWebhookHeaderKey(): string
    {
        return (string) app(BiteshipSettings::class)->get(
            'webhook_header_key',
            config('services.biteship.webhook_header_key', 'X-Biteship-Signature')
        );
    }

    /**
     * Nilai secret yang diharapkan dari Biteship.
     */
    private function getWebhookSecret(): ?string
    {
        return app(BiteshipSettings::class)->get(
            'webhook_secret',
            config('services.biteship.webhook_secret')
        );
    }

    /**
     * Ambil signature yang diterima dari header request.
     */
    private function getIncomingSignature(Request $request): ?string
    {
        $headerKey = $this->getWebhookHeaderKey();

        return $request->header($headerKey)
            ?? $request->header(str_replace('_', '-', $headerKey))
            ?? $request->header('X-Biteship-Signature')
            ?? $request->header('Biteship-Signature')
            ?? $request->header('Signature');
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
