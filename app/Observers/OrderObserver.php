<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\CustomerNotificationService;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Event: Pesanan dibuat (Konfirmasi pesanan)
        CustomerNotificationService::send($order, 'order_created');
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // 1. Pembayaran Berhasil (Pembayaran diterima)
        if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
            CustomerNotificationService::send($order, 'payment_success');
            return;
        }

        // 2. Refund (Informasi proses refund)
        if ($order->wasChanged('payment_status') && in_array($order->payment_status, ['refunded', 'refund'], true)) {
            CustomerNotificationService::send($order, 'order_refunded');
            return;
        }

        // 3. Pesanan Dibatalkan / Kedaluwarsa
        if (
            ($order->wasChanged('status') && $order->status === 'cancelled') ||
            ($order->wasChanged('payment_status') && in_array($order->payment_status, ['cancelled', 'expired', 'failed', 'denied'], true))
        ) {
            CustomerNotificationService::send($order, 'order_cancelled');
            return;
        }

        // 4. Pesanan Dikirim (Sudah dikirim + resi)
        if (
            ($order->wasChanged('shipping_status') && $order->shipping_status === 'shipped') ||
            ($order->wasChanged('tracking_number') && filled($order->tracking_number) && in_array($order->shipping_status, ['shipped', 'processed'], true))
        ) {
            CustomerNotificationService::send($order, 'order_shipped');
            return;
        }

        // 5. Pesanan Selesai (Konfirmasi pesanan selesai)
        if (
            ($order->wasChanged('status') && in_array($order->status, ['completed', 'delivered'], true)) ||
            ($order->wasChanged('shipping_status') && $order->shipping_status === 'delivered')
        ) {
            CustomerNotificationService::send($order, 'order_completed');
            return;
        }

        // 6. Pesanan Diproses (Sedang disiapkan)
        if (
            ($order->wasChanged('shipping_status') && in_array($order->shipping_status, ['processed', 'waiting_shipment'], true) && $order->payment_status === 'paid') ||
            ($order->wasChanged('status') && $order->status === 'processing')
        ) {
            CustomerNotificationService::send($order, 'order_processing');
        }
    }
}
