<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        logger()->info('MIDTRANS WEBHOOK HIT', [
            'payload' => $request->all(),
        ]);

        $serverKey = config('services.midtrans.server_key');

        $midtransOrderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $signatureKey = (string) $request->input('signature_key');

        $validSignature = hash(
            'sha512',
            $midtransOrderId . $statusCode . $grossAmount . $serverKey
        );

        if (! hash_equals($validSignature, $signatureKey)) {
            logger()->warning('MIDTRANS INVALID SIGNATURE', [
                'order_id' => $midtransOrderId,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transactionStatus = (string) $request->input('transaction_status');
        $fraudStatus = (string) $request->input('fraud_status');

        $baseOrderNumber = preg_replace('/-PAY-.+$/', '', $midtransOrderId);

        $order = Order::query()
            ->where('midtrans_order_id', $midtransOrderId)
            ->orWhere('order_number', $midtransOrderId)
            ->orWhere('order_number', $baseOrderNumber)
            ->with(['items.product', 'items.variant'])
            ->first();

        if (! $order) {
            logger()->warning('MIDTRANS ORDER NOT FOUND', [
                'order_id' => $midtransOrderId,
            ]);

            return response()->json(['message' => 'Order not found, ignored'], 200);
        }

        DB::transaction(function () use ($order, $transactionStatus, $fraudStatus) {
            $order->refresh();

            // Jangan pernah downgrade order yang sudah paid dari webhook lama.
            if ($order->payment_status === 'paid') {
                return;
            }

            if ($transactionStatus === 'capture') {
                if ($fraudStatus === 'accept') {
                    $this->markPaid($order);
                }

                return;
            }

            if ($transactionStatus === 'settlement') {
                $this->markPaid($order);
                return;
            }

            if ($transactionStatus === 'pending') {
                $order->update([
                    'status' => 'pending_payment',
                    'payment_status' => 'pending',
                    'shipping_status' => 'pending',
                ]);

                return;
            }

            if ($transactionStatus === 'expire') {
                $order->update([
                    'status' => 'expired',
                    'payment_status' => 'expired',
                    'shipping_status' => 'cancelled',
                ]);

                $this->restoreStock($order);
                return;
            }

            if (in_array($transactionStatus, ['cancel', 'deny', 'failure'], true)) {
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => match ($transactionStatus) {
                        'deny' => 'denied',
                        'failure' => 'failed',
                        default => 'cancelled',
                    },
                    'shipping_status' => 'cancelled',
                ]);

                $this->restoreStock($order);
            }
        });

        return response()->json(['message' => 'OK']);
    }

    private function markPaid(Order $order): void
    {
        $order->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'shipping_status' => 'waiting_shipment',
            'paid_at' => now(),
        ]);
    }

    private function restoreStock(Order $order): void
    {
        $order = Order::query()
            ->whereKey($order->id)
            ->with(['items'])
            ->lockForUpdate()
            ->firstOrFail();

        if ($order->stock_restored_at) {
            return;
        }

        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->increment('stock', $item->qty);

                continue;
            }

            if ($item->product_id) {
                Product::query()
                    ->whereKey($item->product_id)
                    ->increment('stock', $item->qty);
            }
        }

        $order->update([
            'stock_restored_at' => now(),
        ]);
    }
}