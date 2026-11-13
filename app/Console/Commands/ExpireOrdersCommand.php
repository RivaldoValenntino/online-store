<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\StockReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending unpaid orders and release their reserved stock';

    /**
     * Execute the console command.
     */
    public function handle(StockReservationService $stockReservationService): int
    {
        $expiredOrders = Order::query()
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order, $stockReservationService) {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if (! $lockedOrder || $lockedOrder->status !== 'pending_payment') {
                    return;
                }

                // Release reserved stock safely
                $stockReservationService->release($lockedOrder, 'expired');

                // Return voucher quota if used
                if ($lockedOrder->voucher_id) {
                    VoucherUsage::where('order_id', $lockedOrder->id)->delete();
                    $voucher = Voucher::whereKey($lockedOrder->voucher_id)->lockForUpdate()->first();
                    if ($voucher && $voucher->total_used > 0) {
                        $voucher->decrement('total_used');
                    }
                }

                $lockedOrder->update([
                    'status' => 'expired',
                    'payment_status' => 'expired',
                    'shipping_status' => 'cancelled',
                    'stock_restored_at' => $lockedOrder->stock_restored_at ?: now(),
                ]);
            });

            $count++;
            $this->info("Order #{$order->order_number} marked as expired and reserved stock released.");
        }

        $this->info("Total expired orders processed: {$count}");

        return self::SUCCESS;
    }
}
