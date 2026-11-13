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
use App\Services\OrderCalculationService;
use App\Services\ShippingService;
use App\Services\StockReservationService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    protected StockReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.midtrans.server_key' => 'test_server_key',
            'services.midtrans.signature_key' => 'test_server_key',
        ]);

        $this->createDatabaseTables();
        $this->reservationService = app(StockReservationService::class);
    }

    protected function createDatabaseTables(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('addresses')) {
            Schema::create('addresses', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->string('recipient_name');
                $table->string('recipient_phone');
                $table->text('full_address');
                $table->string('province')->nullable();
                $table->string('city')->nullable();
                $table->string('district')->nullable();
                $table->string('subdistrict')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('biteship_area_id')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
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

        if (! Schema::hasTable('products')) {
            Schema::create('products', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price', 15, 2);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0);
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
                $table->integer('reserved_stock')->default(0);
                $table->integer('weight_gram')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
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
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->string('order_number')->unique();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('shipping_cost', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('origin_location_name')->nullable();
                $table->string('origin_contact_name')->nullable();
                $table->string('origin_contact_phone')->nullable();
                $table->text('origin_address')->nullable();
                $table->string('origin_postal_code')->nullable();
                $table->string('origin_area_id')->nullable();
                $table->decimal('origin_latitude', 10, 8)->nullable();
                $table->decimal('origin_longitude', 11, 8)->nullable();
                $table->text('origin_note')->nullable();
                $table->decimal('product_discount', 15, 2)->default(0);
                $table->decimal('shipping_discount', 15, 2)->default(0);
                $table->string('status')->default('pending_payment');
                $table->string('payment_status')->default('pending');
                $table->string('shipping_status')->default('pending');
                $table->string('recipient_name')->nullable();
                $table->string('recipient_phone')->nullable();
                $table->text('shipping_address')->nullable();
                $table->string('shipping_courier')->nullable();
                $table->string('shipping_service')->nullable();
                $table->string('shipping_service_name')->nullable();
                $table->string('shipping_etd')->nullable();
                $table->string('shipping_provider')->nullable();
                $table->timestamp('payment_expires_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('stock_reserved_at')->nullable();
                $table->timestamp('stock_deducted_at')->nullable();
                $table->timestamp('stock_released_at')->nullable();
                $table->timestamp('stock_restored_at')->nullable();
                $table->boolean('stock_admin_review_required')->default(false);
                $table->timestamp('shipment_created_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('voucher_code')->nullable();
                $table->foreignId('voucher_id')->nullable();
                $table->foreignId('address_id')->nullable();
                $table->string('midtrans_order_id')->nullable();
                $table->string('midtrans_snap_token')->nullable();
                $table->text('midtrans_redirect_url')->nullable();
                $table->string('payment_method')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function ($table) {
                $table->id();
                $table->foreignId('order_id');
                $table->foreignId('product_id')->nullable();
                $table->foreignId('product_variant_id')->nullable();
                $table->string('product_name');
                $table->string('variant_name')->nullable();
                $table->string('variant_sku')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('qty')->default(1);
                $table->integer('weight_gram')->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Helper to mock shipping verification in order calculation service.
     */
    protected function createMockOrderCalculationService(): OrderCalculationService
    {
        $mockShipping = Mockery::mock(ShippingService::class);
        $mockShipping->shouldReceive('rates')->andReturn([
            [
                'key' => 'jne:reg',
                'code' => 'jne',
                'service' => 'reg',
                'service_name' => 'Reguler',
                'name' => 'JNE Reguler',
                'cost' => 10000,
                'price' => 10000,
                'etd' => '1-2 hari',
            ],
        ]);

        $mockVoucher = Mockery::mock(VoucherService::class);
        $mockVoucher->shouldReceive('applyVoucherToOrder')->andReturnNull();

        return new OrderCalculationService(
            shippingService: $mockShipping,
            voucherService: $mockVoucher,
            stockReservationService: $this->reservationService
        );
    }

    /**
     * Helper to create a test customer and shipping address.
     */
    protected function createCustomerAndAddress(): array
    {
        $customer = Customer::create([
            'name' => 'Budi Santoso',
            'email' => 'budi.' . uniqid() . '@example.com',
            'phone' => '081234567890',
        ]);

        $address = Address::create([
            'customer_id' => $customer->id,
            'recipient_name' => 'Budi Santoso',
            'recipient_phone' => '081234567890',
            'full_address' => 'Jl. Merdeka No. 10',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'district' => 'Coblong',
            'subdistrict' => 'Dago',
            'postal_code' => '40135',
            'biteship_area_id' => 'IDNP6IDNC401IDND123',
            'is_default' => true,
        ]);

        return [$customer, $address];
    }

    /**
     * Scenario 1: Order pending: physical stock unchanged, reserved stock increased.
     */
    public function test_scenario_1_pending_order_reserves_stock_without_deducting_physical(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Dodol Garut Original',
            'slug' => 'dodol-garut-original',
            'price' => 50000,
            'stock' => 20,
            'reserved_stock' => 0,
            'weight_gram' => 250,
            'is_active' => true,
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 3,
        ]);

        $calcService = $this->createMockOrderCalculationService();

        $order = $calcService->placeOrder(
            customer: $customer,
            address: $address,
            shippingKey: 'jne:reg'
        );

        // Refresh product
        $product->refresh();

        $this->assertEquals(20, $product->stock, 'Physical stock must remain unchanged (20).');
        $this->assertEquals(3, $product->reserved_stock, 'Reserved stock must be 3.');
        $this->assertEquals(17, $product->available_stock, 'Available stock must be 17.');
        $this->assertNotNull($order->stock_reserved_at, 'stock_reserved_at must be populated.');
        $this->assertNull($order->stock_deducted_at, 'stock_deducted_at must still be null.');
    }

    /**
     * Scenario 2: Payment success: physical stock deducted, reserved stock released.
     */
    public function test_scenario_2_successful_payment_deducts_physical_stock_and_releases_reservation(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Dodol Coklat',
            'slug' => 'dodol-coklat',
            'price' => 45000,
            'stock' => 15,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-TEST-002',
            'subtotal' => 90000,
            'grand_total' => 100000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'midtrans_order_id' => 'MID-002',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 45000,
            'qty' => 2,
            'subtotal' => 90000,
        ]);

        // Place reservation
        $this->reservationService->reserve($order);

        $product->refresh();
        $this->assertEquals(15, $product->stock);
        $this->assertEquals(2, $product->reserved_stock);

        // Midtrans settlement webhook
        $payload = [
            'order_id' => 'MID-002',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'MID-002' . '200' . '100000.00' . 'test_server_key'),
        ];

        $request = Request::create('/api/midtrans/webhook', 'POST', $payload);
        $controller = new MidtransWebhookController();
        $controller($request);

        $product->refresh();
        $order->refresh();

        $this->assertEquals(13, $product->stock, 'Physical stock must be reduced by 2 (15 -> 13).');
        $this->assertEquals(0, $product->reserved_stock, 'Reserved stock must be released back to 0.');
        $this->assertEquals(13, $product->available_stock, 'Available stock is 13.');
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('confirmed', $order->status);
        $this->assertNotNull($order->stock_deducted_at, 'stock_deducted_at must be populated.');
    }

    /**
     * Scenario 3: Webhook idempotency: repeated success webhooks do NOT deduct stock multiple times.
     */
    public function test_scenario_3_webhook_idempotency_prevents_duplicate_deductions(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Wajit Garut',
            'slug' => 'wajit-garut',
            'price' => 30000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-TEST-003',
            'subtotal' => 60000,
            'grand_total' => 70000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'midtrans_order_id' => 'MID-003',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 30000,
            'qty' => 2,
            'subtotal' => 60000,
        ]);

        $this->reservationService->reserve($order);

        $payload = [
            'order_id' => 'MID-003',
            'status_code' => '200',
            'gross_amount' => '70000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'MID-003' . '200' . '70000.00' . 'test_server_key'),
        ];

        $controller = new MidtransWebhookController();

        // Send 1st webhook
        $controller(Request::create('/api/midtrans/webhook', 'POST', $payload));

        $product->refresh();
        $this->assertEquals(8, $product->stock);
        $this->assertEquals(0, $product->reserved_stock);

        // Send 2nd identical webhook (duplicate delivery)
        $controller(Request::create('/api/midtrans/webhook', 'POST', $payload));

        // Send 3rd identical webhook
        $controller(Request::create('/api/midtrans/webhook', 'POST', $payload));

        $product->refresh();
        $this->assertEquals(8, $product->stock, 'Physical stock must remain 8 even after multiple identical webhook deliveries.');
        $this->assertEquals(0, $product->reserved_stock);
        $this->assertEquals(8, $product->available_stock);
    }

    /**
     * Scenario 4: Payment expired: reservation released, physical stock unchanged.
     */
    public function test_scenario_4_payment_expired_releases_reserved_stock(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Kue Keranjang Manis',
            'slug' => 'kue-keranjang-manis',
            'price' => 40000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-TEST-004',
            'subtotal' => 40000,
            'grand_total' => 50000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'midtrans_order_id' => 'MID-004',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 40000,
            'qty' => 4,
            'subtotal' => 40000,
        ]);

        $this->reservationService->reserve($order);

        $product->refresh();
        $this->assertEquals(10, $product->stock);
        $this->assertEquals(4, $product->reserved_stock);
        $this->assertEquals(6, $product->available_stock);

        // Midtrans expire webhook
        $payload = [
            'order_id' => 'MID-004',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'expire',
            'signature_key' => hash('sha512', 'MID-004' . '200' . '50000.00' . 'test_server_key'),
        ];

        $controller = new MidtransWebhookController();
        $controller(Request::create('/api/midtrans/webhook', 'POST', $payload));

        $product->refresh();
        $order->refresh();

        $this->assertEquals(10, $product->stock, 'Physical stock was never deducted (still 10).');
        $this->assertEquals(0, $product->reserved_stock, 'Reserved stock released back to 0.');
        $this->assertEquals(10, $product->available_stock, 'Available stock restored to 10.');
        $this->assertEquals('expired', $order->status);
        $this->assertEquals('expired', $order->payment_status);
        $this->assertNotNull($order->stock_released_at);
        $this->assertNull($order->stock_deducted_at);
    }

    /**
     * Scenario 5: Order cancelled: reservation released, physical stock unchanged.
     */
    public function test_scenario_5_order_cancelled_releases_reserved_stock(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Kerupuk Kulit Sapi',
            'slug' => 'kerupuk-kulit-sapi',
            'price' => 25000,
            'stock' => 12,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-TEST-005',
            'subtotal' => 50000,
            'grand_total' => 60000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 25000,
            'qty' => 2,
            'subtotal' => 50000,
        ]);

        $this->reservationService->reserve($order);

        $product->refresh();
        $this->assertEquals(2, $product->reserved_stock);

        // Cancel order
        $released = $this->reservationService->release($order, 'customer_cancelled');
        $order->update(['status' => 'cancelled', 'payment_status' => 'cancelled']);

        $product->refresh();
        $this->assertTrue($released);
        $this->assertEquals(12, $product->stock);
        $this->assertEquals(0, $product->reserved_stock);
        $this->assertEquals(12, $product->available_stock);

        // Calling release a 2nd time must be a safe no-op
        $secondRelease = $this->reservationService->release($order, 'duplicate_cancel');
        $this->assertFalse($secondRelease, 'Duplicate release call must return false and not double-decrement.');
        $product->refresh();
        $this->assertEquals(0, $product->reserved_stock);
    }

    /**
     * Scenario 6: Concurrency / race condition test: two orders checkout limited stock, overselling prevented.
     */
    public function test_scenario_6_overselling_is_prevented_when_stock_is_limited(): void
    {
        [$customerA, $addressA] = $this->createCustomerAndAddress();
        [$customerB, $addressB] = $this->createCustomerAndAddress();

        // Product has only 5 units in stock
        $product = Product::create([
            'name' => 'Hampers Spesial Lebaran Limited',
            'slug' => 'hampers-spesial-limited',
            'price' => 200000,
            'stock' => 5,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $calcService = $this->createMockOrderCalculationService();

        // Customer A carts 4 units
        $cartA = Cart::create(['customer_id' => $customerA->id]);
        CartItem::create([
            'cart_id' => $cartA->id,
            'product_id' => $product->id,
            'qty' => 4,
        ]);

        // Customer A places order successfully
        $orderA = $calcService->placeOrder(
            customer: $customerA,
            address: $addressA,
            shippingKey: 'jne:reg'
        );

        $this->assertNotNull($orderA);
        $product->refresh();
        $this->assertEquals(5, $product->stock);
        $this->assertEquals(4, $product->reserved_stock);
        $this->assertEquals(1, $product->available_stock, 'Only 1 unit available now.');

        // Customer B carts 2 units (exceeds available stock of 1)
        $cartB = Cart::create(['customer_id' => $customerB->id]);
        CartItem::create([
            'cart_id' => $cartB->id,
            'product_id' => $product->id,
            'qty' => 2,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak mencukupi');

        // Customer B attempt must fail validation and not oversell
        $calcService->placeOrder(
            customer: $customerB,
            address: $addressB,
            shippingKey: 'jne:reg'
        );
    }

    /**
     * Scenario 7: Failed order creation: rollback prevents leftover reservation.
     */
    public function test_scenario_7_failed_order_creation_rolls_back_reservation(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Dodol Kacang Kenari',
            'slug' => 'dodol-kacang-kenari',
            'price' => 35000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        try {
            DB::transaction(function () use ($customer, $product) {
                $order = Order::create([
                    'customer_id' => $customer->id,
                    'order_number' => 'ORD-FAIL-001',
                    'subtotal' => 70000,
                    'grand_total' => 70000,
                    'status' => 'pending_payment',
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => 35000,
                    'qty' => 2,
                    'subtotal' => 70000,
                ]);

                $this->reservationService->reserve($order);

                // Simulate an unexpected runtime exception during order flow (e.g. gateway error)
                throw new \RuntimeException('Simulated payment gateway timeout');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $product->refresh();
        $this->assertEquals(10, $product->stock);
        $this->assertEquals(0, $product->reserved_stock, 'Reserved stock must be 0 due to transaction rollback.');
        $this->assertEquals(10, $product->available_stock);
        $this->assertDatabaseMissing('orders', ['order_number' => 'ORD-FAIL-001']);
    }

    /**
     * Scenario 8: Late payment after reservation was released:
     * - Case 8a: physical stock is still available -> deduct physical stock.
     * - Case 8b: physical stock is depleted -> do NOT make negative, flag stock_admin_review_required.
     */
    public function test_scenario_8a_late_payment_with_stock_available_deducts_physical_stock(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Sale Pisang Crispy',
            'slug' => 'sale-pisang-crispy',
            'price' => 20000,
            'stock' => 8,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-LATE-001',
            'subtotal' => 40000,
            'grand_total' => 50000,
            'status' => 'expired',
            'payment_status' => 'expired',
            'stock_reserved_at' => now()->subDay(),
            'stock_released_at' => now()->subHours(2),
            'midtrans_order_id' => 'MID-LATE-001',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 20000,
            'qty' => 2,
            'subtotal' => 40000,
        ]);

        // Midtrans settlement webhook arrives late
        $payload = [
            'order_id' => 'MID-LATE-001',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'MID-LATE-001' . '200' . '50000.00' . 'test_server_key'),
        ];

        $controller = new MidtransWebhookController();
        $controller(Request::create('/api/midtrans/webhook', 'POST', $payload));

        $product->refresh();
        $order->refresh();

        $this->assertEquals(6, $product->stock, 'Physical stock deducted from 8 to 6.');
        $this->assertEquals(0, $product->reserved_stock);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertFalse((bool) $order->stock_admin_review_required, 'No admin review required since stock was available.');
        $this->assertNotNull($order->stock_deducted_at);
    }

    public function test_scenario_8b_late_payment_when_out_of_stock_flags_admin_review_without_negative_stock(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        // Product physical stock is only 1 left (another customer bought it while this order expired)
        $product = Product::create([
            'name' => 'Kue Semprong Premium',
            'slug' => 'kue-semprong-premium',
            'price' => 30000,
            'stock' => 1,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-LATE-002',
            'subtotal' => 90000,
            'grand_total' => 100000,
            'status' => 'expired',
            'payment_status' => 'expired',
            'stock_reserved_at' => now()->subDay(),
            'stock_released_at' => now()->subHours(1),
            'midtrans_order_id' => 'MID-LATE-002',
        ]);

        // Order requires 3 units, but only 1 exists physically
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 30000,
            'qty' => 3,
            'subtotal' => 90000,
        ]);

        $payload = [
            'order_id' => 'MID-LATE-002',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'MID-LATE-002' . '200' . '100000.00' . 'test_server_key'),
        ];

        $controller = new MidtransWebhookController();
        $controller(Request::create('/api/midtrans/webhook', 'POST', $payload));

        $product->refresh();
        $order->refresh();

        $this->assertEquals(1, $product->stock, 'Physical stock must NEVER be negative (remains 1).');
        $this->assertEquals(0, $product->reserved_stock);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertTrue((bool) $order->stock_admin_review_required, 'Admin review required must be true!');
        $this->assertStringContainsString('PERINGATAN', (string) $order->notes);
        $this->assertNull($order->stock_deducted_at, 'stock_deducted_at must remain null because items could not be deducted.');
    }

    /**
     * Scenario 9: Multi-item orders with product variants: accurate per-item reservations & deductions.
     */
    public function test_scenario_9_multi_item_orders_with_product_variants(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        // Standalone product
        $standaloneProduct = Product::create([
            'name' => 'Madu Murni Garut',
            'slug' => 'madu-murni-garut',
            'price' => 80000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        // Product with variants
        $variantParent = Product::create([
            'name' => 'Dodol Aneka Rasa',
            'slug' => 'dodol-aneka-rasa',
            'price' => 30000,
            'stock' => 0, // Stock managed at variant level
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $variantDurian = ProductVariant::create([
            'product_id' => $variantParent->id,
            'name' => 'Rasa Durian',
            'sku' => 'DODOL-DUR-250',
            'price' => 35000,
            'stock' => 8,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $variantNanas = ProductVariant::create([
            'product_id' => $variantParent->id,
            'name' => 'Rasa Nanas',
            'sku' => 'DODOL-NAN-250',
            'price' => 30000,
            'stock' => 6,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $standaloneProduct->id,
            'qty' => 2,
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $variantParent->id,
            'product_variant_id' => $variantDurian->id,
            'qty' => 3,
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $variantParent->id,
            'product_variant_id' => $variantNanas->id,
            'qty' => 1,
        ]);

        $calcService = $this->createMockOrderCalculationService();

        $order = $calcService->placeOrder(
            customer: $customer,
            address: $address,
            shippingKey: 'jne:reg'
        );

        $order->update(['midtrans_order_id' => 'MID-MULTI-001']);

        // Check reservations
        $standaloneProduct->refresh();
        $variantDurian->refresh();
        $variantNanas->refresh();

        $this->assertEquals(10, $standaloneProduct->stock);
        $this->assertEquals(2, $standaloneProduct->reserved_stock);
        $this->assertEquals(8, $standaloneProduct->available_stock);

        $this->assertEquals(8, $variantDurian->stock);
        $this->assertEquals(3, $variantDurian->reserved_stock);
        $this->assertEquals(5, $variantDurian->available_stock);

        $this->assertEquals(6, $variantNanas->stock);
        $this->assertEquals(1, $variantNanas->reserved_stock);
        $this->assertEquals(5, $variantNanas->available_stock);

        // Webhook arrives -> commit
        $payload = [
            'order_id' => 'MID-MULTI-001',
            'status_code' => '200',
            'gross_amount' => (string) $order->grand_total,
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'MID-MULTI-001' . '200' . (string) $order->grand_total . 'test_server_key'),
        ];

        $controller = new MidtransWebhookController();
        $controller(Request::create('/api/midtrans/webhook', 'POST', $payload));

        $standaloneProduct->refresh();
        $variantDurian->refresh();
        $variantNanas->refresh();
        $order->refresh();

        $this->assertEquals(8, $standaloneProduct->stock, 'Standalone product deducted by 2 (10 -> 8).');
        $this->assertEquals(0, $standaloneProduct->reserved_stock);

        $this->assertEquals(5, $variantDurian->stock, 'Durian variant deducted by 3 (8 -> 5).');
        $this->assertEquals(0, $variantDurian->reserved_stock);

        $this->assertEquals(5, $variantNanas->stock, 'Nanas variant deducted by 1 (6 -> 5).');
        $this->assertEquals(0, $variantNanas->reserved_stock);

        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->stock_deducted_at);
    }

    /**
     * Scenario 10: Artisan command orders:expire releases expired orders properly.
     */
    public function test_orders_expire_command_releases_reservations(): void
    {
        [$customer, $address] = $this->createCustomerAndAddress();

        $product = Product::create([
            'name' => 'Wajit Ketan Hitam',
            'slug' => 'wajit-ketan-hitam',
            'price' => 30000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-CMD-EXPIRE-001',
            'subtotal' => 60000,
            'grand_total' => 70000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_expires_at' => now()->subMinutes(10), // expired 10 minutes ago
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 30000,
            'qty' => 2,
            'subtotal' => 60000,
        ]);

        $this->reservationService->reserve($order);

        $product->refresh();
        $this->assertEquals(2, $product->reserved_stock);

        $this->artisan('orders:expire')
            ->expectsOutputToContain('Total expired orders processed: 1')
            ->assertExitCode(0);

        $product->refresh();
        $order->refresh();

        $this->assertEquals(10, $product->stock, 'Physical stock intact.');
        $this->assertEquals(0, $product->reserved_stock, 'Reserved stock released.');
        $this->assertEquals(10, $product->available_stock);
        $this->assertEquals('expired', $order->status);
        $this->assertEquals('expired', $order->payment_status);
        $this->assertNotNull($order->stock_released_at);
    }
}
