<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\ContactSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageRedesignTest extends TestCase
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
            'services.contact.homepage_title' => 'Masih Bingung Memilih Produk?',
            'services.contact.homepage_subtitle' => 'Butuh hampers khusus atau tanya stok?',
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
                $table->unique(['group', 'key']);
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('email_marketing')->default(false);
                $table->boolean('in_app_marketing')->default(false);
                $table->boolean('unsubscribed_from_promos')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('image')->nullable();
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
                $table->text('description')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0);
                $table->integer('weight_gram')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_images')) {
            Schema::create('product_images', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->string('image');
                $table->boolean('is_primary')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->string('name')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function ($table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type');
                $table->decimal('amount', 15, 2);
                $table->decimal('min_spend', 15, 2)->default(0);
                $table->decimal('max_discount', 15, 2)->nullable();
                $table->integer('total_quota')->nullable();
                $table->integer('total_used')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('banners')) {
            Schema::create('banners', function ($table) {
                $table->id();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('badge')->nullable();
                $table->string('image')->nullable();
                $table->string('button_text')->nullable();
                $table->string('button_url')->nullable();
                $table->foreignId('voucher_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
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
                $table->string('status')->default('completed');
                $table->string('payment_status')->default('paid');
                $table->string('shipping_status')->default('pending');
                $table->timestamp('stock_reserved_at')->nullable();
                $table->timestamp('stock_deducted_at')->nullable();
                $table->timestamp('stock_released_at')->nullable();
                $table->boolean('stock_admin_review_required')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function ($table) {
                $table->id();
                $table->foreignId('order_id');
                $table->foreignId('product_id')->nullable();
                $table->string('product_name');
                $table->string('variant_name')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('qty')->default(1);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wishlists')) {
            Schema::create('wishlists', function ($table) {
                $table->id();
                $table->foreignId('customer_id');
                $table->foreignId('product_id');
                $table->timestamps();
                $table->unique(['customer_id', 'product_id']);
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
    }

    public function test_homepage_renders_successfully(): void
    {
        $category = Category::create([
            'name' => 'Dodol Garut Asli',
            'slug' => 'dodol-garut-asli',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Dodol Garut Wijen Super',
            'slug' => 'dodol-garut-wijen-super',
            'price' => 35000,
            'stock' => 50,
            'is_active' => true,
        ]);

        $response = $this->get(route('store.home'));

        $response->assertOk();
        $response->assertSee('Podo Raos');
        $response->assertSee('Dodol Garut Asli');
        $response->assertSee('Dodol Garut Wijen Super');
        $response->assertSee('Kategori');
        $response->assertSee('Produk Terlaris');
        $response->assertSee('Produk Terbaru');
    }

    public function test_authenticated_customer_sees_personalized_header(): void
    {
        $customer = Customer::create([
            'name' => 'Ibu Siti Aminah',
            'email' => 'siti@example.com',
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('store.home'));

        $response->assertOk();
        $response->assertSee('Selamat berbelanja');
        $response->assertSee('Ibu Siti Aminah');
    }

    public function test_active_vouchers_display_in_promo_section(): void
    {
        Voucher::create([
            'code' => 'SAMIRAOS10',
            'name' => 'Diskon Pembukaan 10%',
            'type' => Voucher::TYPE_PERCENTAGE,
            'amount' => 10,
            'min_spend' => 50000,
            'is_active' => true,
            'total_quota' => 100,
            'total_used' => 5,
        ]);

        $response = $this->get(route('store.home'));

        $response->assertOk();
        $response->assertSee('SAMIRAOS10');
        $response->assertSee('Diskon Pembukaan 10%');
        $response->assertSee('10%');
        $response->assertSee('Min. Rp 50.000');
    }

    public function test_best_sellers_order_by_real_paid_sales(): void
    {
        $category = Category::create(['name' => 'Dodol', 'slug' => 'dodol']);

        $productA = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Sedikit Terjual',
            'slug' => 'produk-sedikit',
            'price' => 20000,
            'stock' => 10,
        ]);

        $productB = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Paling Laris',
            'slug' => 'produk-laris',
            'price' => 25000,
            'stock' => 10,
        ]);

        $customer = Customer::create(['name' => 'Pembeli', 'email' => 'buyer@example.com']);

        // Order 1 (Paid) - 5x product B
        $order1 = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-001',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $productB->id,
            'product_name' => $productB->name,
            'qty' => 5,
            'subtotal' => 125000,
        ]);

        // Order 2 (Paid) - 1x product A
        $order2 = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-002',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'qty' => 1,
            'subtotal' => 20000,
        ]);

        $response = $this->get(route('store.home'));
        $response->assertOk();
        $response->assertSee('Produk Paling Laris');
        $response->assertSee('5 terjual');
        $response->assertSee('1 terjual');
    }

    public function test_customer_service_whatsapp_cta_uses_database_settings(): void
    {
        $settings = new ContactSettings();
        $settings->save([
            'cs_name' => 'CS Podo Raos Pusat',
            'cs_phone' => '081234567890',
            'cs_is_active' => true,
        ]);

        $response = $this->get(route('store.home'));

        $response->assertOk();
        $response->assertSee('Masih Bingung Memilih Produk?');
        $response->assertSee('https://wa.me/6281234567890', false);
        $response->assertSee(config('services.contact.homepage_button_text', 'Hubungi CS via WhatsApp'));
        $response->assertSee('style="background-color: #25D366 !important; color: #ffffff !important;"', false);
    }

    public function test_customer_service_whatsapp_cta_reads_text_from_configuration(): void
    {
        config([
            'services.contact.homepage_title' => 'Mau Tanya Stok Dodol Garut?',
            'services.contact.homepage_subtitle' => 'Customer support kami siap membantu!',
            'services.contact.homepage_description' => 'Hubungi kami sekarang untuk penawaran spesial reseller dan hampers.',
            'services.contact.homepage_button_text' => 'Chat WhatsApp Sekarang',
            'services.contact.homepage_message' => 'Halo min, mau tanya harga grosir.',
        ]);

        $settings = new ContactSettings();
        $settings->save([
            'cs_phone' => '081234567890',
            'cs_is_active' => true,
        ]);

        $response = $this->get(route('store.home'));

        $response->assertOk();
        $response->assertSee('Mau Tanya Stok Dodol Garut?');
        $response->assertSee('Customer support kami siap membantu!');
        $response->assertSee('Hubungi kami sekarang untuk penawaran spesial reseller dan hampers.');
        $response->assertSee('Chat WhatsApp Sekarang');
        $response->assertSee('Halo%20min%2C%20mau%20tanya%20harga%20grosir');
        $response->assertSee('style="background-color: #25D366 !important; color: #ffffff !important;"', false);
    }

    public function test_customer_service_shows_graceful_offline_state_when_disabled(): void
    {
        $settings = new ContactSettings();
        $settings->save([
            'cs_phone' => '081234567890',
            'cs_is_active' => false,
        ]);

        $response = $this->get(route('store.home'));

        $response->assertOk();
        $response->assertDontSee('https://wa.me/');
        $response->assertSee('Layanan Customer Service WhatsApp saat ini sedang offline.');
    }

    public function test_homepage_branding_dynamically_reflects_app_name_config(): void
    {
        config([
            'services.apps.app_name' => 'PODO RAOS JAYA',
        ]);

        $response = $this->get(route('store.home'));

        $response->assertOk();
        $response->assertSee('PODO RAOS JAYA');
        $response->assertDontSee('Podo Raos');
    }

    public function test_search_input_renders_properly_with_button(): void
    {
        $response = $this->get(route('store.home'));

        $response->assertOk();
        $response->assertSee('placeholder="Cari dodol, wajit, snack, hampers..."', false);
        $response->assertSee('Cari');
    }
}
