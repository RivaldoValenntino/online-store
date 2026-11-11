<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherService
{
    /**
     * Generate a unique random voucher code.
     */
    public function generateUniqueCode(string $prefix = 'SR', int $randomLength = 6): string
    {
        do {
            $code = strtoupper($prefix . '-' . Str::random($randomLength));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    /**
     * Find voucher by code (case-insensitive).
     */
    public function findByCode(string $code): ?Voucher
    {
        $normalized = strtoupper(trim($code));

        return Voucher::whereRaw('UPPER(code) = ?', [$normalized])->first();
    }

    /**
     * Validate voucher eligibility and calculate discount.
     *
     * @param Collection|array $cartItems
     * @return array [
     *   'valid' => bool,
     *   'message' => string,
     *   'voucher' => ?Voucher,
     *   'product_discount' => float,
     *   'shipping_discount' => float,
     *   'total_discount' => float,
     * ]
     */
    public function validateAndCalculate(
        string|Voucher $voucherOrCode,
        Customer $customer,
        float $subtotal,
        float $shippingCost = 0,
        Collection|array $cartItems = []
    ): array {
        $voucher = is_string($voucherOrCode) ? $this->findByCode($voucherOrCode) : $voucherOrCode;

        if (!$voucher) {
            return [
                'valid' => false,
                'message' => 'Kode voucher tidak ditemukan.',
                'voucher' => null,
                'product_discount' => 0,
                'shipping_discount' => 0,
                'total_discount' => 0,
            ];
        }

        if (!$voucher->is_active) {
            return [
                'valid' => false,
                'message' => 'Voucher sedang tidak aktif.',
                'voucher' => $voucher,
                'product_discount' => 0,
                'shipping_discount' => 0,
                'total_discount' => 0,
            ];
        }

        $now = Carbon::now();

        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            return [
                'valid' => false,
                'message' => 'Voucher baru dapat digunakan mulai ' . $voucher->starts_at->format('d M Y H:i') . '.',
                'voucher' => $voucher,
                'product_discount' => 0,
                'shipping_discount' => 0,
                'total_discount' => 0,
            ];
        }

        if ($voucher->expires_at && $voucher->expires_at->isPast()) {
            return [
                'valid' => false,
                'message' => 'Voucher sudah kedaluwarsa pada ' . $voucher->expires_at->format('d M Y H:i') . '.',
                'voucher' => $voucher,
                'product_discount' => 0,
                'shipping_discount' => 0,
                'total_discount' => 0,
            ];
        }

        if (!is_null($voucher->total_quota) && $voucher->total_used >= $voucher->total_quota) {
            return [
                'valid' => false,
                'message' => 'Kuota penggunaan voucher ini sudah habis.',
                'voucher' => $voucher,
                'product_discount' => 0,
                'shipping_discount' => 0,
                'total_discount' => 0,
            ];
        }

        // Target audience check
        if ($voucher->target_audience === 'new_users') {
            $hasPaidOrders = Order::where('customer_id', $customer->id)
                ->whereIn('payment_status', ['paid'])
                ->exists();

            if ($hasPaidOrders) {
                return [
                    'valid' => false,
                    'message' => 'Voucher ini hanya berlaku untuk pengguna baru.',
                    'voucher' => $voucher,
                    'product_discount' => 0,
                    'shipping_discount' => 0,
                    'total_discount' => 0,
                ];
            }
        }

        // Per user usage limit
        $userLimit = (int) ($voucher->user_limit ?: 1);
        if ($voucher->id && $userLimit > 0) {
            $userUsagesCount = VoucherUsage::where('voucher_id', $voucher->id)
                ->where('customer_id', $customer->id)
                ->count();

            if ($userUsagesCount >= $userLimit) {
                return [
                    'valid' => false,
                    'message' => 'Anda sudah mencapai batas maksimal (' . $userLimit . 'x) penggunaan voucher ini.',
                    'voucher' => $voucher,
                    'product_discount' => 0,
                    'shipping_discount' => 0,
                    'total_discount' => 0,
                ];
            }
        }

        // Minimum spend check
        if ($subtotal < $voucher->min_spend) {
            return [
                'valid' => false,
                'message' => 'Minimum belanja untuk voucher ini adalah Rp ' . number_format($voucher->min_spend, 0, ',', '.') . '.',
                'voucher' => $voucher,
                'product_discount' => 0,
                'shipping_discount' => 0,
                'total_discount' => 0,
            ];
        }

        // Product / Category restrictions check
        $eligibleCategories = array_filter((array) ($voucher->eligible_category_ids ?? []));
        $eligibleProducts = array_filter((array) ($voucher->eligible_product_ids ?? []));

        if (!empty($eligibleCategories) || !empty($eligibleProducts)) {
            $hasEligibleItem = false;
            foreach ($cartItems as $item) {
                $productId = $item->product_id ?? ($item['product_id'] ?? null);
                $categoryId = $item->product?->category_id ?? ($item['category_id'] ?? null);

                if (!empty($eligibleProducts) && in_array($productId, $eligibleProducts)) {
                    $hasEligibleItem = true;
                    break;
                }

                if (!empty($eligibleCategories) && in_array($categoryId, $eligibleCategories)) {
                    $hasEligibleItem = true;
                    break;
                }
            }

            if (!$hasEligibleItem && (count($cartItems) > 0)) {
                return [
                    'valid' => false,
                    'message' => 'Voucher ini hanya berlaku untuk produk atau kategori tertentu.',
                    'voucher' => $voucher,
                    'product_discount' => 0,
                    'shipping_discount' => 0,
                    'total_discount' => 0,
                ];
            }
        }

        // Calculate discount amounts based on type
        $productDiscount = 0.0;
        $shippingDiscount = 0.0;

        switch ($voucher->type) {
            case Voucher::TYPE_PERCENTAGE:
                $calculated = $subtotal * ((float) $voucher->amount / 100);
                if (!is_null($voucher->max_discount) && $voucher->max_discount > 0) {
                    $calculated = min($calculated, (float) $voucher->max_discount);
                }
                $productDiscount = min($calculated, $subtotal);
                break;

            case Voucher::TYPE_FIXED:
                $productDiscount = min((float) $voucher->amount, $subtotal);
                break;

            case Voucher::TYPE_FREE_SHIPPING:
                $shippingDiscount = $shippingCost;
                if (!is_null($voucher->max_discount) && $voucher->max_discount > 0) {
                    $shippingDiscount = min($shippingDiscount, (float) $voucher->max_discount);
                }
                break;

            case Voucher::TYPE_SHIPPING_DISCOUNT:
                $shippingDiscount = min((float) $voucher->amount, $shippingCost);
                if (!is_null($voucher->max_discount) && $voucher->max_discount > 0) {
                    $shippingDiscount = min($shippingDiscount, (float) $voucher->max_discount);
                }
                break;
        }

        $totalDiscount = min($productDiscount + $shippingDiscount, $subtotal + $shippingCost);

        return [
            'valid' => true,
            'message' => 'Voucher "' . $voucher->name . '" berhasil digunakan!',
            'voucher' => $voucher,
            'product_discount' => round($productDiscount, 2),
            'shipping_discount' => round($shippingDiscount, 2),
            'total_discount' => round($totalDiscount, 2),
        ];
    }

    /**
     * Apply voucher to an order with pessimistic locking and transactional integrity.
     */
    public function applyVoucherToOrder(
        Order $order,
        Voucher $voucher,
        Customer $customer,
        float $productDiscount,
        float $shippingDiscount
    ): VoucherUsage {
        return DB::transaction(function () use ($order, $voucher, $customer, $productDiscount, $shippingDiscount) {
            // Lock voucher row to avoid race condition on total_quota
            $lockedVoucher = Voucher::where('id', $voucher->id)->lockForUpdate()->firstOrFail();

            if (!is_null($lockedVoucher->total_quota) && $lockedVoucher->total_used >= $lockedVoucher->total_quota) {
                throw new Exception('Kuota voucher telah habis.');
            }

            // Check user limit inside lock
            $userUsages = VoucherUsage::where('voucher_id', $lockedVoucher->id)
                ->where('customer_id', $customer->id)
                ->count();

            if ($userUsages >= $lockedVoucher->user_limit) {
                throw new Exception('Batas penggunaan voucher per akun telah tercapai.');
            }

            // Check if already applied to this specific order (avoid double insertion on retry)
            $existingUsage = VoucherUsage::where('order_id', $order->id)->first();
            if ($existingUsage) {
                return $existingUsage;
            }

            $totalDiscount = $productDiscount + $shippingDiscount;

            // Increment usage count
            $lockedVoucher->increment('total_used');

            // Record usage
            $usage = VoucherUsage::create([
                'voucher_id' => $lockedVoucher->id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'discount_amount' => $totalDiscount,
            ]);

            // Snapshot voucher data onto order
            $order->update([
                'voucher_id' => $lockedVoucher->id,
                'voucher_code' => $lockedVoucher->code,
                'voucher_discount_type' => $lockedVoucher->type,
                'product_discount' => $productDiscount,
                'shipping_discount' => $shippingDiscount,
                'discount' => $totalDiscount,
                'grand_total' => max(0, $order->subtotal + $order->shipping_cost - $totalDiscount),
            ]);

            return $usage;
        });
    }
}
