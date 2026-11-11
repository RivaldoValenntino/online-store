<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ExpireOrderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $order = Order::query()
                ->whereKey($this->orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return;
            }

            if ($order->payment_status === 'paid') {
                return;
            }

            if ($order->status !== 'pending_payment') {
                return;
            }

            if (! $order->payment_expires_at || now()->lessThan($order->payment_expires_at)) {
                return;
            }

            if (! $order->stock_restored_at) {
                foreach ($order->items()->get() as $item) {
                    if ($item->product_variant_id) {
                        ProductVariant::query()
                            ->whereKey($item->product_variant_id)
                            ->lockForUpdate()
                            ->increment('stock', $item->qty);

                        continue;
                    }

                    if ($item->product_id) {
                        Product::query()
                            ->whereKey($item->product_id)
                            ->lockForUpdate()
                            ->increment('stock', $item->qty);
                    }
                }
            }

            if ($order->voucher_id) {
                VoucherUsage::where('order_id', $order->id)->delete();
                $voucher = Voucher::whereKey($order->voucher_id)->lockForUpdate()->first();
                if ($voucher && $voucher->total_used > 0) {
                    $voucher->decrement('total_used');
                }
            }

            $order->update([
                'status' => 'expired',
                'payment_status' => 'expired',
                'shipping_status' => 'cancelled',
                'stock_restored_at' => $order->stock_restored_at ?: now(),
            ]);
        });
    }
}