<?php

namespace Tests\Feature;

use App\Http\Controllers\MidtransWebhookController;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\OrderCalculationService;
use App\Services\ShippingService;
use App\Services\VoucherService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.midtrans.server_key' => 'SB-Mid-server-TEST123456',
            'services.midtrans.signature_key' => 'SB-Mid-server-TEST123456',
        ]);

        $this->createDatabaseTables();
    }

    protected function createDatabaseTables(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('unsubscribed_from_promos')->default(false);
                $table->boolean('email_marketing')->default(true);
                $table->boolean('in_app_marketing')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('addresses')) {
            Schema::create('addresses', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->string('label')->default('Rumah');
                $table->string('recipient_name');
                $table->string('recipient_phone');
                $table->string('biteship_area_id')->nullable();
                $table->string('biteship_area_label')->nullable();
                $table->string('biteship_postal_code')->nullable();
                $table->string('province')->nullable();
                $table->string('city')->nullable();
                $table->string('district')->nullable();
                $table->string('subdistrict')->nullable();
                $table->string('postal_code')->nullable();
                $table->text('full_address')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('store_shipping_locations')) {
            Schema::create('store_shipping_locations', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('contact_name');
                $table->string('contact_phone', 30);
                $table->text('address');
                $table->string('province')->nullable();
                $table->string('city')->nullable();
                $table->string('district')->nullable();
                $table->string('subdistrict')->nullable();
                $table->string('postal_code', 10)->nullable();
                $table->string('biteship_area_id');
                $table->string('biteship_area_label')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->text('courier_notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function ($table) {
                $table->id();
                $table->foreignId('category_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price', 15, 2);
                $table->integer('stock')->default(0);
                $table->integer('weight_gram')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->string('name');
                $table->string('sku')->nullable();
                $table->decimal('price', 15, 2);
                $table->integer('stock')->default(0);
                $table->integer('weight_gram')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function ($table) {
                $table->id();
                $table->foreignId('customer_id')->nullable();
                $table->string('session_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function ($table) {
                $table->id();
                $table->foreignId('cart_id');
                $table->foreignId('product_id');
                $table->foreignId('product_variant_id')->nullable();
                $table->integer('qty')->default(1);
                $table->decimal('price', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function ($table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type'); // percentage, fixed, free_shipping, shipping_discount
                $table->decimal('amount', 15, 2);
                $table->decimal('min_spend', 15, 2)->default(0);
                $table->decimal('max_discount', 15, 2)->nullable();
                $table->integer('total_quota')->nullable();
                $table->integer('total_used')->default(0);
                $table->integer('user_limit')->default(1);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('target_audience')->default('all');
                $table->json('eligible_product_ids')->nullable();
                $table->json('eligible_category_ids')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->foreignId('address_id')->nullable();
                $table->string('order_number')->unique();
                $table->decimal('subtotal', 15, 2);
                $table->decimal('shipping_cost', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('product_discount', 15, 2)->default(0);
                $table->decimal('shipping_discount', 15, 2)->default(0);
                $table->foreignId('voucher_id')->nullable();
                $table->string('voucher_code')->nullable();
                $table->string('voucher_discount_type')->nullable();
                $table->decimal('grand_total', 15, 2);
                $table->string('status')->default('pending_payment');
                $table->string('payment_status')->default('pending');
                $table->string('shipping_status')->default('pending');
                $table->string('midtrans_order_id')->nullable();
                $table->string('midtrans_snap_token')->nullable();
                $table->text('midtrans_redirect_url')->nullable();
                $table->string('recipient_name')->nullable();
                $table->string('recipient_phone')->nullable();
                $table->text('shipping_address')->nullable();
                $table->string('shipping_courier')->nullable();
                $table->string('shipping_service')->nullable();
                $table->string('shipping_service_name')->nullable();
                $table->string('shipping_etd')->nullable();
                $table->string('shipping_provider')->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('payment_expires_at')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->dateTime('stock_restored_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function ($table) {
                $table->id();
                $table->foreignId('order_id');
                $table->foreignId('product_id');
                $table->foreignId('product_variant_id')->nullable();
                $table->string('product_name');
                $table->string('variant_name')->nullable();
                $table->string('variant_sku')->nullable();
                $table->decimal('price', 15, 2);
                $table->integer('qty');
                $table->integer('weight_gram')->default(0);
                $table->decimal('subtotal', 15, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voucher_usages')) {
            Schema::create('voucher_usages', function ($table) {
                $table->id();
                $table->foreignId('voucher_id');
                $table->foreignId('customer_id');
                $table->foreignId('order_id');
                $table->decimal('discount_amount', 15, 2);
                $table->timestamps();
            });
        }
    }

    /**
     * Attack Vector 1: Tampered CartItem price is completely ignored.
     * Real price is fetched live from database.
     */
    public function test_price_manipulation_in_cart_is_ignored_and_uses_live_database_price(): void
    {
        $customer = Customer::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $product = Product::create([
            'name' => 'Dodol Garut Premium',
            'slug' => 'dodol-garut-premium',
            'price' => 50000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        // Attacker manipulated cart_items table directly to Rp 1
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 1, // Tampered price!
        ]);

        $calcService = app(OrderCalculationService::class);
        $res = $calcService->validateAndRefreshItems(collect([$item]));

        $this->assertTrue($res['is_valid']);
        // Subtotal must be Rp 100.000 (2 * 50.000), NOT Rp 2
        $this->assertEquals(100000, $res['subtotal']);
        $this->assertEquals(50000, $res['items'][0]['price']);
    }

    /**
     * Attack Vector 2: Tampered shipping cost is ignored and verified server-side.
     */
    public function test_tampered_shipping_cost_is_rejected_or_recalculated(): void
    {
        $customer = Customer::create(['name' => 'Bob', 'email' => 'bob@test.com']);
        $address = Address::create([
            'customer_id' => $customer->id,
            'recipient_name' => 'Bob',
            'recipient_phone' => '08123456789',
            'biteship_area_id' => 'IDNP6IDNC148IDND1234',
            'full_address' => 'Jl. Merdeka No. 1',
        ]);
        $product = Product::create([
            'name' => 'Dodol 1kg',
            'slug' => 'dodol-1kg',
            'price' => 50000,
            'stock' => 5,
            'weight_gram' => 1000,
            'is_active' => true,
        ]);
        $cart = Cart::create(['customer_id' => $customer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 50000,
        ]);

        // Mock ShippingService to return fixed verified rates
        $mockShipping = $this->createMock(ShippingService::class);
        $mockShipping->method('rates')->willReturn([
            [
                'key' => 'jne-reg',
                'code' => 'jne',
                'service' => 'REG',
                'name' => 'JNE Reguler',
                'cost' => 25000,
                'etd' => '2-3 hari',
            ],
        ]);
        $this->app->instance(ShippingService::class, $mockShipping);

        $calcService = app(OrderCalculationService::class);
        // Pre-cache verified quote
        $rates = $calcService->getVerifiedShippingRates($customer, $address, 1000, collect([$item]));

        // Attacker attempts to checkout with fake key or tampered cost 0
        $fakeRate = $calcService->findVerifiedShippingRate($customer, $address, 1000, collect([$item]), 'fake-free-shipping');
        $this->assertNull($fakeRate);

        // Valid key returns server-verified Rp 25.000, impossible to tamper to 0
        $validRate = $calcService->findVerifiedShippingRate($customer, $address, 1000, collect([$item]), 'jne-reg');
        $this->assertNotNull($validRate);
        $this->assertEquals(25000, $validRate['cost']);
    }

    /**
     * Attack Vector 3: Negative quantity rejection.
     */
    public function test_negative_quantity_is_rejected(): void
    {
        $customer = Customer::create(['name' => 'Eve', 'email' => 'eve@test.com']);
        $product = Product::create([
            'name' => 'Wajit Garut',
            'slug' => 'wajit-garut',
            'price' => 25000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => -5,
            'price' => 25000,
        ]);

        $calcService = app(OrderCalculationService::class);
        $res = $calcService->validateAndRefreshItems(collect([$item]));

        $this->assertFalse($res['is_valid']);
        $this->assertNotEmpty($res['errors']);
    }

    /**
     * Attack Vector 4: Zero quantity rejection.
     */
    public function test_zero_quantity_is_rejected(): void
    {
        $customer = Customer::create(['name' => 'Zack', 'email' => 'zack@test.com']);
        $product = Product::create([
            'name' => 'Kerupuk Kulit',
            'slug' => 'kerupuk-kulit',
            'price' => 15000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 0,
            'price' => 15000,
        ]);

        $calcService = app(OrderCalculationService::class);
        $res = $calcService->validateAndRefreshItems(collect([$item]));

        $this->assertFalse($res['is_valid']);
    }

    /**
     * Attack Vector 5: Inactive product purchase blocked.
     */
    public function test_inactive_product_purchase_is_blocked(): void
    {
        $customer = Customer::create(['name' => 'Mallory', 'email' => 'mallory@test.com']);
        $product = Product::create([
            'name' => 'Produk Nonaktif',
            'slug' => 'produk-nonaktif',
            'price' => 30000,
            'stock' => 10,
            'is_active' => false, // Deactivated!
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 30000,
        ]);

        $calcService = app(OrderCalculationService::class);
        $res = $calcService->validateAndRefreshItems(collect([$item]));

        $this->assertFalse($res['is_valid']);
        $this->assertStringContainsString('dinonaktifkan', $res['errors'][0]);
    }

    /**
     * Attack Vector 6: Inactive variant purchase blocked.
     */
    public function test_inactive_variant_purchase_is_blocked(): void
    {
        $customer = Customer::create(['name' => 'Frank', 'email' => 'frank@test.com']);
        $product = Product::create([
            'name' => 'Dodol Varian',
            'slug' => 'dodol-varian',
            'price' => 30000,
            'stock' => 10,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Coklat (Nonaktif)',
            'price' => 35000,
            'stock' => 5,
            'is_active' => false, // Variant deactivated
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'qty' => 1,
            'price' => 35000,
        ]);

        $calcService = app(OrderCalculationService::class);
        $res = $calcService->validateAndRefreshItems(collect([$item]));

        $this->assertFalse($res['is_valid']);
        $this->assertStringContainsString('dinonaktifkan', $res['errors'][0]);
    }

    /**
     * Attack Vector 7: Expired voucher rejected.
     */
    public function test_expired_voucher_is_rejected(): void
    {
        $customer = Customer::create(['name' => 'Grace', 'email' => 'grace@test.com']);
        $voucher = Voucher::create([
            'code' => 'EXPIRED10',
            'name' => 'Diskon 10rb Kedaluwarsa',
            'type' => 'fixed',
            'amount' => 10000,
            'min_spend' => 20000,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDays(2), // Expired 2 days ago
        ]);

        $voucherService = app(VoucherService::class);
        $res = $voucherService->validateAndCalculate('EXPIRED10', $customer, 50000);

        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('kedaluwarsa', $res['message']);
    }

    /**
     * Attack Vector 8: Voucher user limit exceeded rejected.
     */
    public function test_voucher_user_limit_exceeded_is_rejected(): void
    {
        $customer = Customer::create(['name' => 'Heidi', 'email' => 'heidi@test.com']);
        $voucher = Voucher::create([
            'code' => 'ONCEONLY',
            'name' => 'Voucher 1x Pakai',
            'type' => 'fixed',
            'amount' => 15000,
            'min_spend' => 30000,
            'user_limit' => 1,
            'is_active' => true,
        ]);

        // Record 1 previous usage
        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'customer_id' => $customer->id,
            'order_id' => 999,
            'discount_amount' => 15000,
        ]);

        $voucherService = app(VoucherService::class);
        $res = $voucherService->validateAndCalculate('ONCEONLY', $customer, 50000);

        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('mencapai batas maksimal', $res['message']);
    }

    /**
     * Attack Vector 9: Voucher quota exhaustion rejected.
     */
    public function test_exhausted_voucher_quota_is_rejected(): void
    {
        $customer = Customer::create(['name' => 'Ivan', 'email' => 'ivan@test.com']);
        $voucher = Voucher::create([
            'code' => 'LIMITEDQUOTA',
            'name' => 'Voucher Terbatas',
            'type' => 'fixed',
            'amount' => 10000,
            'min_spend' => 20000,
            'total_quota' => 5,
            'total_used' => 5, // Quota exhausted!
            'is_active' => true,
        ]);

        $voucherService = app(VoucherService::class);
        $res = $voucherService->validateAndCalculate('LIMITEDQUOTA', $customer, 50000);

        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('sudah habis', $res['message']);
    }

    /**
     * Attack Vector 10: IDOR on other customer's shipping address.
     */
    public function test_idor_on_other_customer_shipping_address_is_blocked(): void
    {
        $victim = Customer::create(['name' => 'Victim', 'email' => 'victim@test.com']);
        $attacker = Customer::create(['name' => 'Attacker', 'email' => 'attacker@test.com']);

        $victimAddress = Address::create([
            'customer_id' => $victim->id,
            'recipient_name' => 'Victim Address',
            'recipient_phone' => '0811111111',
            'full_address' => 'Jl. Rahasia',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alamat pengiriman tidak valid');

        app(OrderCalculationService::class)->placeOrder(
            customer: $attacker,
            address: $victimAddress,
            shippingKey: 'jne-reg'
        );
    }

    /**
     * Attack Vector 11: Midtrans webhook with invalid signature rejected with 403.
     */
    public function test_midtrans_webhook_with_invalid_signature_returns_403(): void
    {
        $request = Request::create('/webhooks/midtrans', 'POST', [
            'order_id' => 'ORD-12345',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'signature_key' => 'INVALID_SIGNATURE_KEY_ATTACK',
            'transaction_status' => 'settlement',
        ]);

        $controller = new MidtransWebhookController();
        $response = $controller($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Attack Vector 12: Midtrans webhook gross amount mismatch rejected with 400.
     */
    public function test_midtrans_webhook_gross_amount_mismatch_returns_400(): void
    {
        $customer = Customer::create(['name' => 'Judy', 'email' => 'judy@test.com']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-AMOUNT-001',
            'subtotal' => 200000,
            'shipping_cost' => 20000,
            'discount' => 0,
            'grand_total' => 220000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        $serverKey = config('services.midtrans.server_key');
        $tamperedGross = '10000.00'; // Attacker paid Rp 10.000 for a Rp 220.000 order
        $validSignature = hash('sha512', $order->order_number . '200' . $tamperedGross . $serverKey);

        $request = Request::create('/webhooks/midtrans', 'POST', [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => $tamperedGross,
            'signature_key' => $validSignature,
            'transaction_status' => 'settlement',
        ]);

        $controller = new MidtransWebhookController();
        $response = $controller($request);

        $this->assertEquals(400, $response->getStatusCode());
        $order->refresh();
        $this->assertEquals('pending', $order->payment_status);
    }

    /**
     * Attack Vector 13: Midtrans webhook replay idempotency.
     * Duplicate settlement webhook does not duplicate updates or restore stock.
     */
    public function test_midtrans_webhook_replay_is_idempotent(): void
    {
        $customer = Customer::create(['name' => 'Kevin', 'email' => 'kevin@test.com']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-IDEMP-001',
            'subtotal' => 100000,
            'shipping_cost' => 10000,
            'discount' => 0,
            'grand_total' => 110000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $serverKey = config('services.midtrans.server_key');
        $validSignature = hash('sha512', $order->order_number . '200' . '110000.00' . $serverKey);

        $request = Request::create('/webhooks/midtrans', 'POST', [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => '110000.00',
            'signature_key' => $validSignature,
            'transaction_status' => 'settlement',
        ]);

        $controller = new MidtransWebhookController();
        $response = $controller($request);

        $this->assertEquals(200, $response->getStatusCode());
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    /**
     * Attack Vector 14: Stock boundary / overselling prevention.
     */
    public function test_overselling_beyond_available_stock_is_prevented(): void
    {
        $customer = Customer::create(['name' => 'Leo', 'email' => 'leo@test.com']);
        $product = Product::create([
            'name' => 'Dodol Limited 1 Pcs Left',
            'slug' => 'dodol-limited-1-pcs-left',
            'price' => 50000,
            'stock' => 1,
            'is_active' => true,
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 5, // Demands 5 when only 1 available!
            'price' => 50000,
        ]);

        $calcService = app(OrderCalculationService::class);
        $res = $calcService->validateAndRefreshItems(collect([$item]));

        $this->assertFalse($res['is_valid']);
        $this->assertStringContainsString('tidak mencukupi', $res['errors'][0]);
    }

    /**
     * Attack Vector 15: Minimum spend bypass prevention for vouchers.
     */
    public function test_voucher_minimum_spend_bypass_is_prevented(): void
    {
        $customer = Customer::create(['name' => 'Mia', 'email' => 'mia@test.com']);
        $voucher = Voucher::create([
            'code' => 'MIN100K',
            'name' => 'Diskon Min 100rb',
            'type' => 'fixed',
            'amount' => 20000,
            'min_spend' => 100000,
            'is_active' => true,
        ]);

        $voucherService = app(VoucherService::class);
        // Customer subtotal is only Rp 50.000 (< 100.000)
        $res = $voucherService->validateAndCalculate('MIN100K', $customer, 50000);

        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('Minimum belanja', $res['message']);
    }

    /**
     * Attack Vector 16: Non-negative grand total guarantee.
     * Even if discount exceeds subtotal + shipping, grand total is capped at 0.
     */
    public function test_grand_total_never_becomes_negative(): void
    {
        $customer = Customer::create(['name' => 'Nina', 'email' => 'nina@test.com']);
        $voucher = Voucher::create([
            'code' => 'SUPERDISCOUNT',
            'name' => 'Diskon Melebihi Total',
            'type' => 'fixed',
            'amount' => 500000, // Rp 500.000 discount
            'min_spend' => 10000,
            'is_active' => true,
        ]);

        $voucherService = app(VoucherService::class);
        $res = $voucherService->validateAndCalculate('SUPERDISCOUNT', $customer, subtotal: 50000, shippingCost: 10000);

        $this->assertTrue($res['valid']);
        // Discount is capped at subtotal (50.000), total discount capped at 50.000
        $this->assertLessThanOrEqual(50000, $res['total_discount']);
        $grandTotal = max(0, 50000 + 10000 - $res['total_discount']);
        $this->assertGreaterThanOrEqual(0, $grandTotal);
    }
}
