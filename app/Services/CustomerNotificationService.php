<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\CustomerOrderNotification;
use Illuminate\Support\Facades\Log;

class CustomerNotificationService
{
    /**
     * Kirim notifikasi in-app dan email ke pelanggan terkait perubahan order.
     */
    public static function send(Order $order, string $event, ?string $extraMessage = null): void
    {
        try {
            $customer = $order->customer;

            if (! $customer) {
                return;
            }

            // Mencegah duplikasi notifikasi untuk order & event yang sama
            $alreadySent = $customer->notifications()
                ->where('data->order_id', $order->id)
                ->where('data->event', $event)
                ->exists();

            if ($alreadySent) {
                return;
            }

            $customer->notify(new CustomerOrderNotification($order, $event, $extraMessage));

            Log::info("CUSTOMER NOTIFICATION SENT [{$event}]", [
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        } catch (\Throwable $e) {
            // Catat log error agar tidak mengganggu transaksi/webhook utama jika SMTP/koneksi bermasalah
            Log::error("CUSTOMER NOTIFICATION FAILED [{$event}]", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
