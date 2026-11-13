<?php

namespace App\Services;

use App\Jobs\ExpireOrderJob;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreShippingLocation;
use App\Models\Voucher;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCalculationService
{
    public function __construct(
        protected ShippingService $shippingService,
        protected VoucherService $voucherService,
        protected StockReservationService $stockReservationService
    ) {}

    /**
     * Cache TTL for server-side verified shipping quotes (15 minutes).
     */
    protected const SHIPPING_CACHE_TTL_MINUTES = 15;

    /**
     * Get or build the customer's cart.
     */
    public function getCustomerCart(Customer $customer): ?Cart
    {
        return Cart::query()
            ->with(['items.product.primaryImage', 'items.variant'])
            ->where('customer_id', $customer->id)
            ->first();
    }

    /**
     * Validate cart items against live database records.
     * Enforces:
     * - Quantities must be positive integers (>= 1)
     * - Product must exist, not be soft-deleted, and be active (is_active = true)
     * - If variant, variant must exist and be active (is_active = true)
     * - Stock must be sufficient
     * - Price is ALWAYS taken from live database record, never from cart_items table
     *
     * @return array [
     *   'is_valid' => bool,
     *   'errors' => array,
     *   'items' => Collection,
     *   'subtotal' => int,
     *   'total_weight' => int
     * ]
     */
    public function validateAndRefreshItems(Collection $cartItems, bool $lockForUpdate = false): array
    {
        $errors = [];
        $validatedItems = collect();
        $subtotal = 0;
        $totalWeight = 0;

        foreach ($cartItems as $item) {
            $qty = (int) $item->qty;
            if ($qty < 1) {
                $errors[] = "Jumlah item untuk produk ID {$item->product_id} tidak valid (minimal 1).";
                continue;
            }

            // Retrieve live Product
            $productQuery = Product::query()->where('id', $item->product_id);
            if ($lockForUpdate) {
                $productQuery->lockForUpdate();
            }
            $product = $productQuery->first();

            if (! $product) {
                $errors[] = 'Salah satu produk di keranjang sudah tidak tersedia.';
                continue;
            }

            if (! $product->is_active) {
                $errors[] = "Produk \"{$product->name}\" saat ini sedang dinonaktifkan.";
                continue;
            }

            $variant = null;
            $unitPrice = (int) round((float) $product->price);
            $weightGram = (int) ($product->weight_gram ?? 0);
            $availableStock = (int) $product->available_stock;

            if ($item->product_variant_id) {
                $variantQuery = ProductVariant::query()
                    ->where('id', $item->product_variant_id)
                    ->where('product_id', $product->id);
                if ($lockForUpdate) {
                    $variantQuery->lockForUpdate();
                }
                $variant = $variantQuery->first();

                if (! $variant) {
                    $errors[] = "Varian produk \"{$product->name}\" sudah tidak tersedia.";
                    continue;
                }

                if (! $variant->is_active) {
                    $errors[] = "Varian \"{$variant->name}\" dari produk \"{$product->name}\" sedang dinonaktifkan.";
                    continue;
                }

                $unitPrice = (int) round((float) $variant->price);
                $weightGram = (int) ($variant->weight_gram ?? $weightGram);
                $availableStock = (int) $variant->available_stock;
            }

            if ($availableStock < $qty) {
                $itemName = $variant ? "{$product->name} ({$variant->name})" : $product->name;
                $errors[] = "Stok untuk \"{$itemName}\" tidak mencukupi (tersedia: {$availableStock}, diminta: {$qty}).";
                continue;
            }

            $lineSubtotal = $unitPrice * $qty;
            $subtotal += $lineSubtotal;
            $totalWeight += ($weightGram * $qty);

            $validatedItems->push([
                'cart_item_id' => $item->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product' => $product,
                'variant' => $variant,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'variant_sku' => $variant?->sku,
                'price' => $unitPrice,
                'qty' => $qty,
                'weight_gram' => $weightGram,
                'subtotal' => $lineSubtotal,
            ]);
        }

        return [
            'is_valid' => empty($errors) && $validatedItems->isNotEmpty(),
            'errors' => $errors,
            'items' => $validatedItems,
            'subtotal' => max(0, $subtotal),
            'total_weight' => max(0, $totalWeight),
        ];
    }

    /**
     * Cache key for verified shipping quotes.
     */
    protected function getShippingCacheKey(int $customerId, int $addressId, int $totalWeight, ?string $originKey = null): string
    {
        return "verified_shipping_{$customerId}_{$addressId}_{$totalWeight}_{$originKey}";
    }

    /**
     * Retrieve or query Biteship shipping rates and cache them server-side.
     */
    public function getVerifiedShippingRates(Customer $customer, Address $address, int $totalWeight, Collection $cartItems): array
    {
        if ($totalWeight <= 0 || ! $address->biteship_area_id) {
            return [];
        }

        $origin = StoreShippingLocation::getDefault();
        $originKey = $origin ? "loc_{$origin->id}_{$origin->biteship_area_id}" : 'default';

        $cacheKey = $this->getShippingCacheKey($customer->id, $address->id, $totalWeight, $originKey);

        return Cache::remember($cacheKey, now()->addMinutes(self::SHIPPING_CACHE_TTL_MINUTES), function () use ($address, $cartItems, $totalWeight, $origin) {
            $rates = $this->shippingService->rates(
                address: $address,
                items: $cartItems,
                totalWeight: $totalWeight,
                customOrigin: $origin
            );

            // Sanitize rates so costs are non-negative integers
            return collect($rates)->map(function ($rate) {
                $rate['cost'] = max(0, (int) round((float) ($rate['cost'] ?? 0)));
                return $rate;
            })->values()->all();
        });
    }

    /**
     * Find a verified shipping option by key.
     * If cache misses, re-queries ShippingService to prevent false rejections.
     */
    public function findVerifiedShippingRate(Customer $customer, Address $address, int $totalWeight, Collection $cartItems, string $shippingKey): ?array
    {
        $rates = $this->getVerifiedShippingRates($customer, $address, $totalWeight, $cartItems);

        $match = collect($rates)->firstWhere('key', $shippingKey);

        if (! $match) {
            $origin = StoreShippingLocation::getDefault();
            $originKey = $origin ? "loc_{$origin->id}_{$origin->biteship_area_id}" : 'default';
            // Bust cache and re-fetch once to ensure newly selected options are fresh
            Cache::forget($this->getShippingCacheKey($customer->id, $address->id, $totalWeight, $originKey));
            $freshRates = $this->getVerifiedShippingRates($customer, $address, $totalWeight, $cartItems);
            $match = collect($freshRates)->firstWhere('key', $shippingKey);
        }

        return $match;
    }

    /**
     * Perform comprehensive calculation and verification of an order.
     */
    public function calculate(
        Customer $customer,
        Collection $rawCartItems,
        ?Address $address = null,
        ?string $shippingKey = null,
        ?string $voucherCode = null,
        bool $lockForUpdate = false
    ): array {
        // 1. Validate & live-price cart items
        $itemValidation = $this->validateAndRefreshItems($rawCartItems, $lockForUpdate);
        $errors = $itemValidation['errors'];
        $subtotal = $itemValidation['subtotal'];
        $totalWeight = $itemValidation['total_weight'];
        $verifiedItems = $itemValidation['items'];

        // 2. Shipping cost verification
        $shippingOption = null;
        $shippingCost = 0;

        if ($address && $shippingKey) {
            if ($address->customer_id !== $customer->id) {
                $errors[] = 'Alamat pengiriman tidak valid.';
            } else {
                $shippingOption = $this->findVerifiedShippingRate($customer, $address, $totalWeight, $rawCartItems, $shippingKey);
                if (! $shippingOption) {
                    $errors[] = 'Opsi pengiriman yang dipilih tidak valid atau sudah kedaluwarsa.';
                } else {
                    $shippingCost = max(0, (int) round((float) $shippingOption['cost']));
                }
            }
        }

        // 3. Voucher verification & discount calculation
        $appliedVoucher = null;
        $productDiscount = 0;
        $shippingDiscount = 0;
        $totalDiscount = 0;
        $voucherResult = null;

        if ($voucherCode) {
            $voucherResult = $this->voucherService->validateAndCalculate(
                voucherOrCode: $voucherCode,
                customer: $customer,
                subtotal: (float) $subtotal,
                shippingCost: (float) $shippingCost,
                cartItems: $verifiedItems
            );

            if ($voucherResult['valid']) {
                $appliedVoucher = $voucherResult['voucher'];
                $productDiscount = max(0, (int) round((float) ($voucherResult['product_discount'] ?? 0)));
                $shippingDiscount = max(0, (int) round((float) ($voucherResult['shipping_discount'] ?? 0)));
                // Cap discounts to avoid negative values
                $productDiscount = min($productDiscount, $subtotal);
                $shippingDiscount = min($shippingDiscount, $shippingCost);
                $totalDiscount = min($productDiscount + $shippingDiscount, $subtotal + $shippingCost);
            } else {
                $errors[] = $voucherResult['message'];
            }
        }

        // 4. Grand Total calculation (Strict integer, non-negative)
        $grandTotal = max(0, (int) ($subtotal + $shippingCost - $totalDiscount));

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'items' => $verifiedItems,
            'subtotal' => $subtotal,
            'total_weight' => $totalWeight,
            'shipping_option' => $shippingOption,
            'shipping_cost' => $shippingCost,
            'voucher' => $appliedVoucher,
            'voucher_result' => $voucherResult,
            'product_discount' => $productDiscount,
            'shipping_discount' => $shippingDiscount,
            'discount' => $totalDiscount,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Atomically process checkout and place order with strict database locking.
     * Prevents race conditions on stock and voucher quota.
     *
     * @throws Exception
     */
    public function placeOrder(
        Customer $customer,
        Address $address,
        string $shippingKey,
        ?string $voucherCode = null,
        ?string $notes = null
    ): Order {
        if ($address->customer_id !== $customer->id) {
            throw new Exception('Alamat pengiriman tidak valid untuk akun ini.');
        }

        return DB::transaction(function () use ($customer, $address, $shippingKey, $voucherCode, $notes) {
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->with(['items'])
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new Exception('Keranjang belanja kosong.');
            }

            // Calculate with lockForUpdate = true to lock product & variant rows
            $calc = $this->calculate(
                customer: $customer,
                rawCartItems: $cart->items,
                address: $address,
                shippingKey: $shippingKey,
                voucherCode: $voucherCode,
                lockForUpdate: true
            );

            if (! $calc['is_valid']) {
                throw new Exception(implode(' ', $calc['errors']));
            }

            $shipping = $calc['shipping_option'];
            if (! $shipping) {
                throw new Exception('Opsi pengiriman tidak valid.');
            }

            $appliedVoucher = $calc['voucher'];
            $orderNumber = 'ORD-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
            $origin = StoreShippingLocation::getDefault();

            $order = Order::create([
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'order_number' => $orderNumber,

                // Origin snapshot
                'origin_location_name' => $origin?->name ?? ('Toko ' . (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'))),
                'origin_contact_name' => $origin?->contact_name ?? (string) config('services.biteship.origin_contact_name'),
                'origin_contact_phone' => $origin?->contact_phone ?? (string) config('services.biteship.origin_contact_phone'),
                'origin_address' => $origin?->address ?? (string) config('services.biteship.origin_address'),
                'origin_postal_code' => $origin?->postal_code ?? (string) config('services.biteship.origin_postal_code'),
                'origin_area_id' => $origin?->biteship_area_id ?? (string) config('services.biteship.origin_area_id'),
                'origin_latitude' => $origin?->latitude,
                'origin_longitude' => $origin?->longitude,
                'origin_note' => $origin?->courier_notes,

                'subtotal' => $calc['subtotal'],
                'shipping_cost' => $calc['shipping_cost'],
                'discount' => $calc['discount'],
                'product_discount' => $calc['product_discount'],
                'shipping_discount' => $calc['shipping_discount'],
                'voucher_id' => $appliedVoucher?->id,
                'voucher_code' => $appliedVoucher?->code,
                'voucher_discount_type' => $appliedVoucher?->type,
                'grand_total' => $calc['grand_total'],

                'status' => $calc['grand_total'] === 0 ? 'confirmed' : 'pending_payment',
                'payment_status' => $calc['grand_total'] === 0 ? 'paid' : 'pending',
                'shipping_status' => 'pending',
                'payment_expires_at' => now()->addHours(24),
                'paid_at' => $calc['grand_total'] === 0 ? now() : null,
                'notes' => $notes ?: null,

                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->recipient_phone,
                'shipping_address' => collect([
                    $address->full_address,
                    $address->subdistrict,
                    $address->district,
                    $address->city,
                    $address->province,
                    $address->postal_code,
                ])->filter()->implode(', '),

                'shipping_courier' => $shipping['code'],
                'shipping_service' => $shipping['service'],
                'shipping_service_name' => $shipping['service_name'] ?? ($shipping['name'] ?? null),
                'shipping_etd' => $shipping['etd'],
                'shipping_provider' => 'biteship',
            ]);

            // If voucher applied, record usage and decrement quota under lock
            if ($appliedVoucher) {
                $this->voucherService->applyVoucherToOrder(
                    order: $order,
                    voucher: $appliedVoucher,
                    customer: $customer,
                    productDiscount: (float) $calc['product_discount'],
                    shippingDiscount: (float) $calc['shipping_discount']
                );
            }

            // Create OrderItems
            foreach ($calc['items'] as $itemData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['product_variant_id'],
                    'product_name' => $itemData['product_name'],
                    'variant_name' => $itemData['variant_name'],
                    'variant_sku' => $itemData['variant_sku'],
                    'price' => $itemData['price'],
                    'qty' => $itemData['qty'],
                    'weight_gram' => $itemData['weight_gram'],
                    'subtotal' => $itemData['subtotal'],
                ]);
            }

            // Reserve stock (holds quantity, physical stock untouched)
            $this->stockReservationService->reserve($order);

            // If order is free ($0), immediately commit deduction
            if ($calc['grand_total'] === 0) {
                $this->stockReservationService->commit($order);
            } else {
                ExpireOrderJob::dispatch($order->id)->delay($order->payment_expires_at);
            }

            // Empty cart
            $cart->items()->delete();

            return $order;
        });
    }
}
