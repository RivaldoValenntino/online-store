<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StockReservationService
{
    /**
     * Reserve stock for an order (called during checkout).
     * Must be called within a database transaction with items locked.
     */
    public function reserve(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $qty = (int) $item->qty;

            if ($item->product_variant_id) {
                $variant = ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    throw new RuntimeException("Varian produk ID {$item->product_variant_id} tidak ditemukan.");
                }

                $available = max(0, (int) $variant->stock - (int) ($variant->reserved_stock ?? 0));
                if ($available < $qty) {
                    $name = $item->variant_name ? "{$item->product_name} ({$item->variant_name})" : $item->product_name;
                    throw new RuntimeException("Stok untuk \"{$name}\" tidak mencukupi (tersedia: {$available}, diminta: {$qty}).");
                }

                $variant->increment('reserved_stock', $qty);
                continue;
            }

            if ($item->product_id) {
                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw new RuntimeException("Produk ID {$item->product_id} tidak ditemukan.");
                }

                $available = max(0, (int) $product->stock - (int) ($product->reserved_stock ?? 0));
                if ($available < $qty) {
                    throw new RuntimeException("Stok untuk \"{$item->product_name}\" tidak mencukupi (tersedia: {$available}, diminta: {$qty}).");
                }

                $product->increment('reserved_stock', $qty);
            }
        }

        $order->update([
            'stock_reserved_at' => now(),
        ]);
    }

    /**
     * Commit stock deduction when payment succeeds (Midtrans webhook / instant confirmation).
     * Idempotent: safe against duplicate calls.
     * Decrements physical stock and decrements reserved stock.
     */
    public function commit(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->with(['items'])
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: if physical stock was already deducted, do not deduct again
            if ($lockedOrder->stock_deducted_at) {
                return true;
            }

            // If reservation was already released (e.g. late payment after expiration), handle via late payment
            if ($lockedOrder->stock_released_at) {
                $result = $this->handleLatePayment($lockedOrder);
                return (bool) ($result['success'] ?? false);
            }

            foreach ($lockedOrder->items as $item) {
                $qty = (int) $item->qty;

                if ($item->product_variant_id) {
                    $variant = ProductVariant::query()
                        ->whereKey($item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($variant) {
                        $newStock = max(0, (int) $variant->stock - $qty);
                        $newReserved = max(0, (int) ($variant->reserved_stock ?? 0) - $qty);
                        $variant->update([
                            'stock' => $newStock,
                            'reserved_stock' => $newReserved,
                        ]);
                    }
                    continue;
                }

                if ($item->product_id) {
                    $product = Product::query()
                        ->whereKey($item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $newStock = max(0, (int) $product->stock - $qty);
                        $newReserved = max(0, (int) ($product->reserved_stock ?? 0) - $qty);
                        $product->update([
                            'stock' => $newStock,
                            'reserved_stock' => $newReserved,
                        ]);
                    }
                }
            }

            $lockedOrder->update([
                'stock_deducted_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Release reserved stock when order is cancelled, expired, or payment fails.
     * Idempotent: safe against duplicate calls.
     * Does NOT touch physical stock.
     */
    public function release(Order $order, string $reason = 'cancelled'): bool
    {
        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->with(['items'])
                ->lockForUpdate()
                ->firstOrFail();

            // If already released, or if physical stock was already deducted, do NOT release again
            if ($lockedOrder->stock_released_at || $lockedOrder->stock_deducted_at) {
                return false;
            }

            // If stock was never reserved, nothing to release
            if (! $lockedOrder->stock_reserved_at) {
                return false;
            }

            foreach ($lockedOrder->items as $item) {
                $qty = (int) $item->qty;

                if ($item->product_variant_id) {
                    $variant = ProductVariant::query()
                        ->whereKey($item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($variant) {
                        $newReserved = max(0, (int) ($variant->reserved_stock ?? 0) - $qty);
                        $variant->update(['reserved_stock' => $newReserved]);
                    }
                    continue;
                }

                if ($item->product_id) {
                    $product = Product::query()
                        ->whereKey($item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $newReserved = max(0, (int) ($product->reserved_stock ?? 0) - $qty);
                        $product->update(['reserved_stock' => $newReserved]);
                    }
                }
            }

            $lockedOrder->update([
                'stock_released_at' => now(),
                'stock_restored_at' => now(), // preserve compatibility with existing checks
            ]);

            Log::info("Order #{$lockedOrder->order_number} stock reservation released. Reason: {$reason}");

            return true;
        });
    }

    /**
     * Handle payment received after reservation was already released/expired (Late Payment).
     * Locks products, verifies if physical stock is still available:
     * - If sufficient: deducts physical stock directly, sets stock_deducted_at = now().
     * - If insufficient: does NOT make stock negative. Flags stock_admin_review_required = true.
     */
    public function handleLatePayment(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->with(['items'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->stock_deducted_at) {
                return [
                    'success' => true,
                    'admin_review_required' => (bool) $lockedOrder->stock_admin_review_required,
                    'note' => 'Stok sudah diproses sebelumnya.',
                ];
            }

            $allAvailable = true;
            $insufficientItems = [];

            // 1st pass: verify physical availability without over-committing reserved stock
            foreach ($lockedOrder->items as $item) {
                $qty = (int) $item->qty;

                $model = $item->product_variant_id
                    ? ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first()
                    : Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

                $available = $model ? max(0, (int) $model->stock - (int) ($model->reserved_stock ?? 0)) : 0;
                if ($available < $qty) {
                    $allAvailable = false;
                    $itemName = $item->product_name . ($item->variant_name ? " ({$item->variant_name})" : '');
                    $insufficientItems[] = "{$itemName} (tersedia: {$available}, diminta: {$qty})";
                }
            }

            if ($allAvailable) {
                // Deduct physical stock
                foreach ($lockedOrder->items as $item) {
                    $qty = (int) $item->qty;

                    if ($item->product_variant_id) {
                        ProductVariant::query()
                            ->whereKey($item->product_variant_id)
                            ->lockForUpdate()
                            ->decrement('stock', $qty);
                        continue;
                    }

                    if ($item->product_id) {
                        Product::query()
                            ->whereKey($item->product_id)
                            ->lockForUpdate()
                            ->decrement('stock', $qty);
                    }
                }

                $lockedOrder->update([
                    'stock_deducted_at' => now(),
                    'stock_admin_review_required' => false,
                ]);

                return [
                    'success' => true,
                    'admin_review_required' => false,
                    'note' => 'Stok fisik berhasil dipotong setelah verifikasi ketersediaan.',
                ];
            }

            $note = 'Stok tidak mencukupi untuk pemenuhan otomatis: ' . implode(', ', $insufficientItems);
            $lockedOrder->update([
                'stock_admin_review_required' => true,
            ]);

            Log::warning("Late payment for Order #{$lockedOrder->order_number} requires admin stock review: {$note}");

            return [
                'success' => false,
                'admin_review_required' => true,
                'note' => $note,
            ];
        });
    }

    /**
     * Compute available stock for a product or variant.
     */
    public function getAvailableStock(Product|ProductVariant $model): int
    {
        return max(0, (int) $model->stock - (int) ($model->reserved_stock ?? 0));
    }
}
