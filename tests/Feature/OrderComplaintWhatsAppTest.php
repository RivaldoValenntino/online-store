<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ContactSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class OrderComplaintWhatsAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.apps.app_name' => 'Podo Raos',
            'app.name' => 'Podo Raos',
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

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->string('order_number')->unique();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('shipping_cost', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('status')->default('pending_payment');
                $table->string('payment_status')->default('pending');
                $table->string('shipping_status')->default('pending');
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
                $table->decimal('origin_latitude', 10, 8)->nullable();
                $table->decimal('origin_longitude', 11, 8)->nullable();
                $table->string('voucher_code')->nullable();
                $table->foreignId('voucher_id')->nullable();
                $table->foreignId('address_id')->nullable();
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

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->unsignedBigInteger('notifiable_id');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['notifiable_type', 'notifiable_id']);
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
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->foreignId('customer_id')->nullable();
                $table->foreignId('order_id')->nullable();
                $table->foreignId('order_item_id')->nullable();
                $table->tinyInteger('rating')->unsigned()->default(5);
                $table->text('review')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->boolean('is_verified_purchase')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_review_images')) {
            Schema::create('product_review_images', function ($table) {
                $table->id();
                $table->foreignId('product_review_id');
                $table->string('image_path');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function test_phone_number_normalization(): void
    {
        $this->assertSame('6281234567890', ContactSettings::normalizePhone('081234567890'));
        $this->assertSame('6281234567890', ContactSettings::normalizePhone('+62 812-3456-7890'));
        $this->assertSame('6281234567890', ContactSettings::normalizePhone('6281234567890'));
        $this->assertSame('6281234567890', ContactSettings::normalizePhone('81234567890'));
        $this->assertNull(ContactSettings::normalizePhone('12345')); // too short
        $this->assertNull(ContactSettings::normalizePhone(''));
        $this->assertNull(ContactSettings::normalizePhone(null));
    }

    public function test_contact_settings_save_and_retrieve(): void
    {
        $service = new ContactSettings();

        $service->save([
            'cs_name' => 'CS Podo Raos Garut',
            'cs_phone' => '089876543210',
            'cs_is_active' => true,
        ]);

        $this->assertTrue($service->isAvailable());
        $this->assertSame('CS Podo Raos Garut', $service->get('cs_name'));
        $this->assertSame('6289876543210', $service->get('cs_phone'));
        $this->assertTrue($service->get('cs_is_active'));
    }

    public function test_whatsapp_message_content_builder(): void
    {
        $service = new ContactSettings();
        $service->save([
            'cs_name' => 'CS Podo Raos',
            'cs_phone' => '081234567890',
            'cs_is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-20261112-9988',
            'subtotal' => 120000,
            'shipping_cost' => 15000,
            'discount' => 10000,
            'grand_total' => 125000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'shipping_status' => 'delivered',
            'created_at' => Carbon::parse('2026-11-12 10:30:00'),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Dodol Garut Original',
            'variant_name' => '500 gram',
            'price' => 50000,
            'qty' => 2,
            'subtotal' => 100000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Wajit Ketan Legit',
            'variant_name' => null,
            'price' => 20000,
            'qty' => 1,
            'subtotal' => 20000,
        ]);

        $message = $service->buildComplaintMessage($order);

        $this->assertStringContainsString('Halo Podo Raos, saya ingin mengajukan komplain terkait pesanan saya.', $message);
        $this->assertStringContainsString('ORD-20261112-9988', $message);
        $this->assertStringContainsString('Dodol Garut Original (500 gram) (2x)', $message);
        $this->assertStringContainsString('Wajit Ketan Legit (1x)', $message);
        $this->assertStringContainsString('Rp 125.000', $message);
        $this->assertStringContainsString('Pesanan Selesai', $message);
        $this->assertStringContainsString('Kendala yang saya alami:', $message);
        $this->assertStringContainsString('Mohon bantuannya untuk pengecekan. Terima kasih.', $message);

        $url = $service->getComplaintUrl($order);
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $url);
        $this->assertStringContainsString(rawurlencode('ORD-20261112-9988'), $url);
    }

    public function test_whatsapp_complaint_url_returns_null_when_cs_inactive_or_empty(): void
    {
        $service = new ContactSettings();

        // When inactive
        $service->save([
            'cs_name' => 'CS Podo Raos',
            'cs_phone' => '081234567890',
            'cs_is_active' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Ani',
            'email' => 'ani@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-001',
            'grand_total' => 50000,
            'status' => 'pending_payment',
        ]);

        $this->assertFalse($service->isAvailable());
        $this->assertNull($service->getComplaintUrl($order));

        // When phone is empty
        $service->save([
            'cs_phone' => '',
            'cs_is_active' => true,
        ]);

        $this->assertFalse($service->isAvailable());
        $this->assertNull($service->getComplaintUrl($order));
    }

    public function test_order_status_is_never_mutated_by_complaint_url_generation(): void
    {
        $service = new ContactSettings();
        $service->save([
            'cs_phone' => '081234567890',
            'cs_is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-MUTATION-CHECK',
            'grand_total' => 85000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'shipping_status' => 'processing',
        ]);

        $url = $service->getComplaintUrl($order);
        $this->assertNotEmpty($url);

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->shipping_status);
    }

    public function test_customer_cannot_access_other_customer_order_detail(): void
    {
        $customerA = Customer::create([
            'name' => 'Customer A',
            'email' => 'a@example.com',
        ]);

        $customerB = Customer::create([
            'name' => 'Customer B',
            'email' => 'b@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customerA->id,
            'order_number' => 'ORD-SECRET-A',
            'grand_total' => 100000,
        ]);

        // Accessing as customer B must return 403 Forbidden
        $response = $this->actingAs($customerB, 'customer')
            ->get(route('store.orders.show', $order));

        $response->assertStatus(403);
    }

    public function test_customer_can_see_complaint_button_on_own_order(): void
    {
        $service = new ContactSettings();
        $service->save([
            'cs_name' => 'CS Podo Raos',
            'cs_phone' => '081234567890',
            'cs_is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Owner Customer',
            'email' => 'owner@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-OWNER-123',
            'grand_total' => 75000,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Kopi Garut',
            'price' => 75000,
            'qty' => 1,
            'subtotal' => 75000,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('store.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Ajukan Komplain via WhatsApp');
        $response->assertSee('https://wa.me/6281234567890', false);
        $response->assertSee('ORD-OWNER-123');
    }

    public function test_order_detail_shows_fallback_when_cs_is_inactive(): void
    {
        $service = new ContactSettings();
        $service->save([
            'cs_name' => 'CS Podo Raos',
            'cs_phone' => '081234567890',
            'cs_is_active' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Owner Customer 2',
            'email' => 'owner2@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-OWNER-456',
            'grand_total' => 50000,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('store.orders.show', $order));

        $response->assertOk();
        $response->assertDontSee('https://wa.me/');
        $response->assertSee('Layanan Customer Service WhatsApp saat ini sedang tidak aktif atau belum dikonfigurasi.');
    }
}
