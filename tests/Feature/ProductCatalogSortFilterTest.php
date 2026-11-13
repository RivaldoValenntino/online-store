<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogSortFilterTest extends TestCase
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
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

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

        if (! Schema::hasTable('products')) {
            Schema::create('products', function ($table) {
                $table->id();
                $table->foreignId('category_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->decimal('rating_avg', 3, 2)->default(0.00);
                $table->unsignedInteger('reviews_count')->default(0);
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
                $table->string('sku')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0);
                $table->integer('weight_gram')->default(100);
                $table->string('image')->nullable();
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
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('status')->default('completed');
                $table->string('payment_status')->default('paid');
                $table->string('shipping_status')->default('delivered');
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
            });
        }

        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->foreignId('customer_id');
                $table->foreignId('order_id')->nullable();
                $table->foreignId('order_item_id')->nullable();
                $table->unsignedTinyInteger('rating');
                $table->text('review')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
                $table->boolean('is_verified_purchase')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Test catalog renders and contains sort and filter trigger buttons.
     */
    public function test_catalog_renders_sort_and_filter_buttons(): void
    {
        Product::create([
            'name' => 'Dodol Garut Asli',
            'slug' => 'dodol-garut-asli',
            'price' => 30000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->get(route('store.products'));

        $response->assertOk();
        $response->assertSee('Semua Produk');
        $response->assertSee('Urutkan:');
        $response->assertSee('Filter');
        $response->assertSee('Dodol Garut Asli');
    }

    /**
     * Test sorting by latest products.
     */
    public function test_sorting_by_latest(): void
    {
        $old = Product::create([
            'name' => 'Produk Lama',
            'slug' => 'produk-lama',
            'price' => 20000,
            'stock' => 5,
            'created_at' => now()->subDays(5),
            'is_active' => true,
        ]);

        $new = Product::create([
            'name' => 'Produk Baru',
            'slug' => 'produk-baru',
            'price' => 25000,
            'stock' => 5,
            'created_at' => now(),
            'is_active' => true,
        ]);

        Livewire::test('pages::store.products', ['sort' => 'latest'])
            ->assertSeeInOrder(['Produk Baru', 'Produk Lama']);
    }

    /**
     * Test sorting by bestselling based on actual order items sales.
     */
    public function test_sorting_by_bestselling(): void
    {
        $customer = Customer::create(['name' => 'Buyer', 'email' => 'buyer@test.com']);

        $productLowSales = Product::create([
            'name' => 'Dodol Kurang Laris',
            'slug' => 'dodol-kurang-laris',
            'price' => 20000,
            'stock' => 50,
            'is_active' => true,
        ]);

        $productHighSales = Product::create([
            'name' => 'Dodol Super Laris',
            'slug' => 'dodol-super-laris',
            'price' => 30000,
            'stock' => 50,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-BEST-001',
            'payment_status' => 'paid',
        ]);

        // 10 units for productHighSales, 1 unit for productLowSales
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productHighSales->id,
            'product_name' => $productHighSales->name,
            'price' => 30000,
            'qty' => 10,
            'subtotal' => 300000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productLowSales->id,
            'product_name' => $productLowSales->name,
            'price' => 20000,
            'qty' => 1,
            'subtotal' => 20000,
        ]);

        Livewire::test('pages::store.products', ['sort' => 'bestselling'])
            ->assertSeeInOrder(['Dodol Super Laris', 'Dodol Kurang Laris']);
    }

    /**
     * Test sorting by lowest price and highest price.
     */
    public function test_sorting_by_price_asc_and_desc(): void
    {
        $cheap = Product::create([
            'name' => 'Kue Murah',
            'slug' => 'kue-murah',
            'price' => 15000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $expensive = Product::create([
            'name' => 'Hampers Mahal',
            'slug' => 'hampers-mahal',
            'price' => 150000,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Price Ascending: cheap first
        Livewire::test('pages::store.products', ['sort' => 'price_asc'])
            ->assertSeeInOrder(['Kue Murah', 'Hampers Mahal']);

        // Price Descending: expensive first
        Livewire::test('pages::store.products', ['sort' => 'price_desc'])
            ->assertSeeInOrder(['Hampers Mahal', 'Kue Murah']);
    }

    /**
     * Test sorting by highest rating.
     */
    public function test_sorting_by_rating_desc(): void
    {
        $lowRated = Product::create([
            'name' => 'Produk Rating Rendah',
            'slug' => 'produk-rating-rendah',
            'price' => 20000,
            'rating_avg' => 2.5,
            'reviews_count' => 2,
            'stock' => 10,
            'is_active' => true,
        ]);

        $highRated = Product::create([
            'name' => 'Produk Rating Tinggi',
            'slug' => 'produk-rating-tinggi',
            'price' => 20000,
            'rating_avg' => 4.9,
            'reviews_count' => 15,
            'stock' => 10,
            'is_active' => true,
        ]);

        Livewire::test('pages::store.products', ['sort' => 'rating_desc'])
            ->assertSeeInOrder(['Produk Rating Tinggi', 'Produk Rating Rendah']);
    }

    /**
     * Test filtering by category.
     */
    public function test_filtering_by_category(): void
    {
        $catDodol = Category::create(['name' => 'Dodol', 'slug' => 'dodol']);
        $catKeripik = Category::create(['name' => 'Keripik', 'slug' => 'keripik']);

        Product::create([
            'category_id' => $catDodol->id,
            'name' => 'Dodol Wijen',
            'slug' => 'dodol-wijen',
            'price' => 25000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $catKeripik->id,
            'name' => 'Keripik Singkong',
            'slug' => 'keripik-singkong',
            'price' => 15000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Livewire::test('pages::store.products', ['category' => 'dodol'])
            ->assertSee('Dodol Wijen')
            ->assertDontSee('Keripik Singkong');
    }

    /**
     * Test filtering by price range (min and max).
     */
    public function test_filtering_by_price_range(): void
    {
        Product::create([
            'name' => 'Snack Terlalu Murah',
            'slug' => 'snack-terlalu-murah',
            'price' => 8000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Snack Pas di Range',
            'slug' => 'snack-pas-di-range',
            'price' => 25000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Snack Terlalu Mahal',
            'slug' => 'snack-terlalu-mahal',
            'price' => 80000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Livewire::test('pages::store.products', [
            'price_min' => '20000',
            'price_max' => '50000',
        ])
            ->assertSee('Snack Pas di Range')
            ->assertDontSee('Snack Terlalu Murah')
            ->assertDontSee('Snack Terlalu Mahal');
    }

    /**
     * Test filtering by stock availability (in_stock and out_of_stock).
     */
    public function test_filtering_by_stock_availability(): void
    {
        // In stock
        Product::create([
            'name' => 'Produk Stok Tersedia',
            'slug' => 'produk-stok-tersedia',
            'price' => 20000,
            'stock' => 10,
            'reserved_stock' => 2, // available = 8
            'is_active' => true,
        ]);

        // Out of stock (all physical stock reserved)
        Product::create([
            'name' => 'Produk Stok Habis',
            'slug' => 'produk-stok-habis',
            'price' => 25000,
            'stock' => 5,
            'reserved_stock' => 5, // available = 0
            'is_active' => true,
        ]);

        // Filter in_stock
        Livewire::test('pages::store.products', ['stock_status' => 'in_stock'])
            ->assertSee('Produk Stok Tersedia')
            ->assertDontSee('Produk Stok Habis');

        // Filter out_of_stock
        Livewire::test('pages::store.products', ['stock_status' => 'out_of_stock'])
            ->assertSee('Produk Stok Habis')
            ->assertDontSee('Produk Stok Tersedia');
    }

    /**
     * Test filtering by minimum rating (3+ and 4+).
     */
    public function test_filtering_by_minimum_rating(): void
    {
        Product::create([
            'name' => 'Produk Bintang 2',
            'slug' => 'produk-bintang-2',
            'price' => 20000,
            'rating_avg' => 2.0,
            'stock' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Produk Bintang 3.5',
            'slug' => 'produk-bintang-3-5',
            'price' => 20000,
            'rating_avg' => 3.5,
            'stock' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Produk Bintang 4.8',
            'slug' => 'produk-bintang-4-8',
            'price' => 20000,
            'rating_avg' => 4.8,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Rating min 4+
        Livewire::test('pages::store.products', ['rating_min' => '4'])
            ->assertSee('Produk Bintang 4.8')
            ->assertDontSee('Produk Bintang 3.5')
            ->assertDontSee('Produk Bintang 2');

        // Rating min 3+
        Livewire::test('pages::store.products', ['rating_min' => '3'])
            ->assertSee('Produk Bintang 4.8')
            ->assertSee('Produk Bintang 3.5')
            ->assertDontSee('Produk Bintang 2');
    }

    /**
     * Test active filter chips and individual removal.
     */
    public function test_active_filter_chips_and_removal(): void
    {
        $cat = Category::create(['name' => 'Wajit Garut', 'slug' => 'wajit-garut']);

        $component = Livewire::test('pages::store.products', [
            'category' => 'wajit-garut',
            'stock_status' => 'in_stock',
        ]);

        // Chips must be displayed
        $component->assertSee('Wajit Garut')
            ->assertSee('Tersedia');

        // Remove category filter
        $component->call('removeFilter', 'category');

        $this->assertEquals('', $component->get('category'));
        $this->assertEquals('in_stock', $component->get('stock_status'));

        // Reset all filters
        $component->call('resetFilters');
        $this->assertEquals('all', $component->get('stock_status'));
    }

    /**
     * Test empty state displayed when no products match filter.
     */
    public function test_empty_state_displayed_when_no_products_match(): void
    {
        Product::create([
            'name' => 'Dodol Nanas',
            'slug' => 'dodol-nanas',
            'price' => 30000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Livewire::test('pages::store.products', [
            'price_min' => '500000', // impossible price
        ])
            ->assertSee('Produk tidak ditemukan')
            ->assertSee('Reset Filter & Pencarian', false);
    }
}
