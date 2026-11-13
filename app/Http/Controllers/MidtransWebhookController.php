<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockReservationService;
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
        $signatureSecret = config('services.midtrans.signature_key') ?: $serverKey;

        $midtransOrderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $signatureKey = (string) $request->input('signature_key');

        $transactionStatus = (string) $request->input('transaction_status');
        $fraudStatus = (string) $request->input('fraud_status');

        $validSignature1 = hash(
            'sha512',
            $midtransOrderId . $statusCode . $grossAmount . $serverKey
        );
        $validSignature2 = hash(
            'sha512',
            $midtransOrderId . $statusCode . $grossAmount . $signatureSecret
        );

        $isSignatureValid = hash_equals($validSignature1, $signatureKey) || hash_equals($validSignature2, $signatureKey);

        // Jika hash lokal tidak cocok (misal karena perbedaan Signature Secret Key di Midtrans),
        // lakukan fallback verifikasi langsung ke API Midtrans (standar resmi SDK Midtrans).
        if (!$isSignatureValid && !empty($serverKey)) {
            try {
                \Midtrans\Config::$serverKey = $serverKey;
                \Midtrans\Config::$isProduction = (bool) config('services.midtrans.is_production');

                $statusIdentifier = $request->input('transaction_id') ?: $midtransOrderId;
                $statusResponse = \Midtrans\Transaction::status($statusIdentifier);

                if (!empty($statusResponse->order_id)) {
                    $isSignatureValid = true;
                    $transactionStatus = (string) $statusResponse->transaction_status;
                    $fraudStatus = (string) ($statusResponse->fraud_status ?? $fraudStatus);

                    logger()->info('MIDTRANS SIGNATURE VERIFIED VIA API STATUS CHECK', [
                        'order_id' => $midtransOrderId,
                        'transaction_status' => $transactionStatus,
                    ]);
                }
            } catch (\Throwable $e) {
                logger()->warning('MIDTRANS API STATUS CHECK FAILED', [
                    'order_id' => $midtransOrderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$isSignatureValid) {
            logger()->warning('MIDTRANS INVALID SIGNATURE', [
                'order_id' => $midtransOrderId,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $baseOrderNumber = preg_replace('/-PAY-.+$/', '', $midtransOrderId);

        $order = Order::query()
            ->where('midtrans_order_id', $midtransOrderId)
            ->orWhere('order_number', $midtransOrderId)
            ->orWhere('order_number', $baseOrderNumber)
            ->with(['items.product', 'items.variant'])
            ->first();

        if (!$order) {
            logger()->warning('MIDTRANS ORDER NOT FOUND', [
                'order_id' => $midtransOrderId,
            ]);

            return response()->json(['message' => 'Order not found, ignored'], 200);
        }

        // Security Hardening: Ensure paid gross_amount strictly matches order grand_total
        $incomingGross = (int) round((float) $grossAmount);
        $orderGrandTotal = (int) round((float) $order->grand_total);

        if ($incomingGross !== $orderGrandTotal) {
            logger()->warning('MIDTRANS WEBHOOK GROSS AMOUNT MISMATCH', [
                'order_id' => $midtransOrderId,
                'order_grand_total' => $orderGrandTotal,
                'incoming_gross_amount' => $incomingGross,
            ]);

            return response()->json(['message' => 'Gross amount mismatch'], 400);
        }

        DB::transaction(function () use ($order, $transactionStatus, $fraudStatus) {
            $order->refresh();

            // Jangan pernah downgrade order yang sudah paid dari webhook lama.
            if ($order->payment_status === 'paid') {
                return;
            }

            // Jika pesanan sudah kedaluwarsa, dibatalkan, atau reservasi sudah dilepas, tangani late payment
            $isAlreadyCancelledOrExpired = in_array($order->payment_status, ['expired', 'cancelled'], true)
                || in_array($order->status, ['expired', 'cancelled'], true)
                || ! is_null($order->stock_released_at);

            if ($transactionStatus === 'capture') {
                if ($fraudStatus === 'accept') {
                    if ($isAlreadyCancelledOrExpired) {
                        $this->handleLatePayment($order);
                    } else {
                        $this->markPaid($order);
                    }
                }

                return;
            }

            if ($transactionStatus === 'settlement') {
                if ($isAlreadyCancelledOrExpired) {
                    $this->handleLatePayment($order);
                } else {
                    $this->markPaid($order);
                }
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

                $this->restoreStock($order, 'expired');
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

                $this->restoreStock($order, $transactionStatus);
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
            'paid_at' => $order->paid_at ?? now(),
        ]);

        app(StockReservationService::class)->commit($order);
    }

    private function handleLatePayment(Order $order): void
    {
        $lateResult = app(StockReservationService::class)->handleLatePayment($order);

        $order->update([
            'payment_status' => 'paid',
            'paid_at' => $order->paid_at ?? now(),
            'notes' => trim(($order->notes ?? '') . ' [PERINGATAN: Pembayaran diterima setelah pesanan dibatalkan/kedaluwarsa. ' . ($lateResult['note'] ?? '') . ']'),
            'stock_admin_review_required' => $lateResult['admin_review_required'] ?? false,
        ]);
    }

    private function restoreStock(Order $order, string $reason = 'cancelled'): void
    {
        app(StockReservationService::class)->release($order, $reason);
    }
}
