<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreShippingLocation;
use App\Models\User;
use App\Services\BiteshipService;
use App\Services\OrderCalculationService;
use App\Services\ShippingService;
use Exception;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoreShippingLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->createDatabaseTables();
    }

    protected function createDatabaseTables(): void
    {
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function ($table) {
                $table->id();
                $table->string('group');
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->boolean('is_active')->default(true);
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

        if (! Schema::hasTable('addresses')) {
            Schema::create('addresses', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->string('recipient_name');
                $table->string('recipient_phone');
                $table->string('biteship_area_id')->nullable();
                $table->string('biteship_postal_code')->nullable();
                $table->string('postal_code')->nullable();
                $table->text('full_address')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
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
                $table->string('name')->nullable();
                $table->string('sku')->nullable();
                $table->decimal('price', 15, 2)->default(0);
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
                $table->decimal('price', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->foreignId('address_id')->nullable();
                $table->string('order_number')->unique();
                $table->string('origin_location_name')->nullable();
                $table->string('origin_contact_name')->nullable();
                $table->string('origin_contact_phone')->nullable();
                $table->text('origin_address')->nullable();
                $table->string('origin_postal_code')->nullable();
                $table->string('origin_area_id')->nullable();
                $table->decimal('origin_latitude', 10, 8)->nullable();
                $table->decimal('origin_longitude', 11, 8)->nullable();
                $table->text('origin_note')->nullable();
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
                $table->dateTime('stock_reserved_at')->nullable();
                $table->dateTime('stock_deducted_at')->nullable();
                $table->dateTime('stock_released_at')->nullable();
                $table->dateTime('stock_restored_at')->nullable();
                $table->boolean('stock_admin_review_required')->default(false);
                $table->string('midtrans_order_id')->nullable();
                $table->string('midtrans_snap_token')->nullable();
                $table->text('midtrans_redirect_url')->nullable();
                $table->string('payment_method')->nullable();
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
    }

    /**
     * 1. Lokasi pengiriman berhasil dibuat dengan data lengkap.
     */
    public function test_store_shipping_location_can_be_created_with_full_details(): void
    {
        $location = StoreShippingLocation::create([
            'name' => 'Gudang Utama Garut',
            'contact_name' => 'Admin Gudang Podo Raos',
            'contact_phone' => '081234567890',
            'address' => 'Jl. Cimanuk No. 123, Tarogong Kidul',
            'province' => 'Jawa Barat',
            'city' => 'Garut',
            'district' => 'Tarogong Kidul',
            'subdistrict' => 'Sukagalih',
            'postal_code' => '44151',
            'biteship_area_id' => 'IDNP6IDNC148IDND1234',
            'biteship_area_label' => 'Sukagalih, Tarogong Kidul, Garut, Jawa Barat 44151',
            'latitude' => -7.2185240,
            'longitude' => 107.8994510,
            'courier_notes' => 'Pintu gudang cat abu-abu samping minimarket',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('store_shipping_locations', [
            'id' => $location->id,
            'name' => 'Gudang Utama Garut',
            'biteship_area_id' => 'IDNP6IDNC148IDND1234',
            'is_default' => true,
        ]);
        $this->assertEquals('Sukagalih, Tarogong Kidul, Garut, Jawa Barat 44151', $location->biteship_area_label);
    }

    /**
     * 2. Lokasi default berhasil ditentukan dan diambil oleh helper getDefault().
     */
    public function test_default_shipping_location_is_retrieved_correctly(): void
    {
        $loc1 = StoreShippingLocation::create([
            'name' => 'Toko Cabang',
            'contact_name' => 'Budi',
            'contact_phone' => '0811111111',
            'address' => 'Jl. Braga',
            'biteship_area_id' => 'AREA_BANDUNG',
            'is_active' => true,
            'is_default' => false,
        ]);

        $loc2 = StoreShippingLocation::create([
            'name' => 'Gudang Pusat',
            'contact_name' => 'Siti',
            'contact_phone' => '0822222222',
            'address' => 'Jl. Cimanuk',
            'biteship_area_id' => 'AREA_GARUT',
            'is_active' => true,
            'is_default' => true,
        ]);

        $default = StoreShippingLocation::getDefault();
        $this->assertNotNull($default);
        $this->assertEquals($loc2->id, $default->id);
        $this->assertEquals('Gudang Pusat', $default->name);
    }

    /**
     * 3. Tidak ada dua lokasi default yang bertentangan (menjadikan default baru membatalkan default lama).
     */
    public function test_setting_new_default_unsets_previous_default(): void
    {
        $loc1 = StoreShippingLocation::create([
            'name' => 'Lokasi A',
            'contact_name' => 'PIC A',
            'contact_phone' => '081',
            'address' => 'Alamat A',
            'biteship_area_id' => 'AREA_A',
            'is_active' => true,
            'is_default' => true,
        ]);

        $loc2 = StoreShippingLocation::create([
            'name' => 'Lokasi B',
            'contact_name' => 'PIC B',
            'contact_phone' => '082',
            'address' => 'Alamat B',
            'biteship_area_id' => 'AREA_B',
            'is_active' => true,
            'is_default' => false,
        ]);

        $loc2->makeDefault();

        $loc1->refresh();
        $loc2->refresh();

        $this->assertFalse($loc1->is_default);
        $this->assertTrue($loc2->is_default);
        $this->assertEquals(1, StoreShippingLocation::where('is_default', true)->count());
    }

    /**
     * 4. Lokasi default wajib berstatus aktif (otomatis diaktifkan jika dijadikan default).
     */
    public function test_default_location_must_be_active(): void
    {
        $loc = StoreShippingLocation::create([
            'name' => 'Lokasi C',
            'contact_name' => 'PIC C',
            'contact_phone' => '083',
            'address' => 'Alamat C',
            'biteship_area_id' => 'AREA_C',
            'is_active' => false,
            'is_default' => true,
        ]);

        $loc->refresh();
        $this->assertTrue($loc->is_active);
    }

    /**
     * 5. Rates API menggunakan origin_area_id dan koordinat dari database.
     */
    public function test_rates_api_uses_origin_from_store_shipping_location_database(): void
    {
        $location = StoreShippingLocation::create([
            'name' => 'Gudang Pusat Garut',
            'contact_name' => 'Admin Podo Raos',
            'contact_phone' => '0812345678',
            'address' => 'Jl. Garut',
            'postal_code' => '44151',
            'biteship_area_id' => 'BITESHIP_ORIGIN_GARUT_123',
            'latitude' => -7.218524,
            'longitude' => 107.899451,
            'is_active' => true,
            'is_default' => true,
        ]);

        $customer = Customer::create(['name' => 'Customer Test', 'email' => 'cust@test.com']);
        $address = Address::create([
            'customer_id' => $customer->id,
            'recipient_name' => 'Budi',
            'recipient_phone' => '08987654321',
            'biteship_area_id' => 'BITESHIP_DEST_JAKARTA_456',
            'postal_code' => '10110',
            'full_address' => 'Jl. Sudirman No. 1, Jakarta',
        ]);

        $product = Product::create([
            'name' => 'Dodol Garut',
            'slug' => 'dodol-garut',
            'price' => 50000,
            'stock' => 10,
            'weight_gram' => 1000,
            'is_active' => true,
        ]);

        $mockBiteship = $this->createMock(BiteshipService::class);
        $mockBiteship->expects($this->once())
            ->method('rates')
            ->with($this->callback(function (array $payload) use ($location, $address) {
                return $payload['origin_area_id'] === $location->biteship_area_id
                    && $payload['destination_area_id'] === $address->biteship_area_id
                    && isset($payload['origin_latitude'])
                    && (float) $payload['origin_latitude'] === -7.218524;
            }))
            ->willReturn([
                [
                    'key' => 'jne-reg',
                    'code' => 'jne',
                    'name' => 'JNE Reguler',
                    'service' => 'REG',
                    'cost' => 20000,
                    'etd' => '2-3 hari',
                ],
            ]);

        $this->app->instance(BiteshipService::class, $mockBiteship);

        $shippingService = app(ShippingService::class);
        $results = $shippingService->rates($address, collect([['product' => $product, 'qty' => 1]]), 1000);

        $this->assertNotEmpty($results);
        $this->assertEquals(20000, $results[0]['cost']);
    }

    /**
     * 6. Create Shipment menggunakan origin yang sama dari snapshot order.
     */
    public function test_create_shipment_uses_origin_snapshot_from_order(): void
    {
        $order = Order::create([
            'customer_id' => 1,
            'order_number' => 'ORD-TEST-ORIGIN-001',
            'subtotal' => 100000,
            'shipping_cost' => 20000,
            'grand_total' => 120000,
            'recipient_name' => 'Penerima Jakarta',
            'recipient_phone' => '0812999999',
            'shipping_address' => 'Jl. Gatot Subroto No. 5',
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',

            // Origin snapshot stored at checkout
            'origin_location_name' => 'Gudang Utama Garut',
            'origin_contact_name' => 'Pak Asep',
            'origin_contact_phone' => '0813333333',
            'origin_address' => 'Jl. Patriot No. 10 Garut',
            'origin_postal_code' => '44151',
            'origin_area_id' => 'AREA_GARUT_SNAPSHOT',
            'origin_latitude' => -7.2000,
            'origin_longitude' => 107.9000,
            'origin_note' => 'Penjemputan pintu barat',
        ]);

        $address = Address::create([
            'customer_id' => 1,
            'recipient_name' => 'Penerima Jakarta',
            'recipient_phone' => '0812999999',
            'biteship_area_id' => 'AREA_JAKARTA_DEST',
            'postal_code' => '12930',
        ]);
        $order->update(['address_id' => $address->id]);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.biteship.com/v1/orders' => \Illuminate\Support\Facades\Http::response([
                'id' => 'BITESHIP_ORDER_999',
            ], 200),
        ]);

        $res = app(BiteshipService::class)->createOrder($order);
        $this->assertEquals('BITESHIP_ORDER_999', $res['id']);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            return $data['origin_area_id'] === 'AREA_GARUT_SNAPSHOT'
                && $data['origin_contact_name'] === 'Pak Asep'
                && $data['origin_contact_phone'] === '0813333333'
                && $data['origin_address'] === 'Jl. Patriot No. 10 Garut'
                && $data['origin_note'] === 'Penjemputan pintu barat';
        });
    }

    /**
     * 7. Perubahan alamat toko di masa depan tidak mengubah snapshot order lama.
     */
    public function test_modifying_store_location_does_not_mutate_existing_order_snapshot(): void
    {
        $location = StoreShippingLocation::create([
            'name' => 'Gudang Lama',
            'contact_name' => 'Kontak Lama',
            'contact_phone' => '0811111111',
            'address' => 'Alamat Toko Lama',
            'biteship_area_id' => 'AREA_OLD_123',
            'is_active' => true,
            'is_default' => true,
        ]);

        $customer = Customer::create(['name' => 'User A', 'email' => 'usera@test.com']);
        $address = Address::create([
            'customer_id' => $customer->id,
            'recipient_name' => 'Penerima',
            'recipient_phone' => '0822222222',
            'biteship_area_id' => 'AREA_DEST',
            'full_address' => 'Jl. Tujuan',
        ]);
        $product = Product::create([
            'name' => 'Item A',
            'slug' => 'item-a',
            'price' => 50000,
            'stock' => 10,
            'weight_gram' => 500,
            'is_active' => true,
        ]);
        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'qty' => 1, 'price' => 50000]);

        // Mock shipping service rate check
        $mockShipping = $this->createMock(ShippingService::class);
        $mockShipping->method('rates')->willReturn([
            ['key' => 'jne-reg', 'code' => 'jne', 'service' => 'REG', 'cost' => 15000, 'etd' => '1-2 hari'],
        ]);
        $this->app->instance(ShippingService::class, $mockShipping);

        // Place order
        $order = app(OrderCalculationService::class)->placeOrder(
            customer: $customer,
            address: $address,
            shippingKey: 'jne-reg'
        );

        $this->assertEquals('AREA_OLD_123', $order->origin_area_id);
        $this->assertEquals('Gudang Lama', $order->origin_location_name);

        // Admin updates the location address or sets a new warehouse
        $location->update([
            'name' => 'Gudang Baru Yang Dipindah',
            'biteship_area_id' => 'AREA_NEW_999',
            'address' => 'Alamat Pindah Baru',
        ]);

        // Verify order snapshot remains untouched
        $order->refresh();
        $this->assertEquals('AREA_OLD_123', $order->origin_area_id);
        $this->assertEquals('Gudang Lama', $order->origin_location_name);
        $this->assertEquals('Alamat Toko Lama', $order->origin_address);
    }

    /**
     * 8. Regular customer/user cannot modify store address (guarded by admin auth).
     */
    public function test_regular_customer_cannot_access_or_modify_store_location(): void
    {
        $customer = Customer::create(['name' => 'Hacker Customer', 'email' => 'hacker@test.com']);

        // Authenticated as customer guard
        $this->actingAs($customer, 'customer');

        // Accessing Filament admin resources without admin web guard returns redirect to admin login or 403
        $response = $this->get('/admin/store-shipping-locations');
        $this->assertTrue(in_array($response->getStatusCode(), [302, 403, 404]));
    }

    /**
     * 9. Lokasi nonaktif tidak dipakai untuk checkout.
     */
    public function test_inactive_store_location_is_not_used_for_default(): void
    {
        $locInactive = StoreShippingLocation::create([
            'name' => 'Gudang Tutup',
            'contact_name' => 'Tutup',
            'contact_phone' => '08000',
            'address' => 'Alamat Tutup',
            'biteship_area_id' => 'AREA_INACTIVE',
            'is_active' => false,
            'is_default' => false,
        ]);

        $locActive = StoreShippingLocation::create([
            'name' => 'Gudang Aktif Pengganti',
            'contact_name' => 'Aktif',
            'contact_phone' => '08111',
            'address' => 'Alamat Aktif',
            'biteship_area_id' => 'AREA_ACTIVE',
            'is_active' => true,
            'is_default' => true,
        ]);

        $default = StoreShippingLocation::getDefault();
        $this->assertEquals($locActive->id, $default->id);
        $this->assertNotEquals($locInactive->id, $default->id);
    }

    /**
     * 10. Perhitungan ongkir tidak dapat dimanipulasi melalui frontend.
     */
    public function test_shipping_cost_calculation_cannot_be_manipulated_from_frontend(): void
    {
        $location = StoreShippingLocation::create([
            'name' => 'Gudang Garut',
            'contact_name' => 'Admin',
            'contact_phone' => '08123',
            'address' => 'Alamat Toko',
            'biteship_area_id' => 'AREA_GARUT',
            'is_active' => true,
            'is_default' => true,
        ]);

        $customer = Customer::create(['name' => 'Bob', 'email' => 'bob@test.com']);
        $address = Address::create([
            'customer_id' => $customer->id,
            'recipient_name' => 'Bob',
            'recipient_phone' => '0812345',
            'biteship_area_id' => 'AREA_BOB',
            'full_address' => 'Jl. Rumah Bob',
        ]);
        $product = Product::create([
            'name' => 'Dodol Legit',
            'slug' => 'dodol-legit',
            'price' => 50000,
            'stock' => 10,
            'weight_gram' => 500,
            'is_active' => true,
        ]);
        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'qty' => 1, 'price' => 50000]);

        $mockShipping = $this->createMock(ShippingService::class);
        $mockShipping->method('rates')->willReturn([
            ['key' => 'jne-reg', 'code' => 'jne', 'service' => 'REG', 'cost' => 25000, 'etd' => '2-3 hari'],
        ]);
        $this->app->instance(ShippingService::class, $mockShipping);

        // Attacker attempts to checkout with fake shipping key
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Opsi pengiriman yang dipilih tidak valid');

        app(OrderCalculationService::class)->placeOrder(
            customer: $customer,
            address: $address,
            shippingKey: 'fake-zero-shipping-cost'
        );
    }
}
