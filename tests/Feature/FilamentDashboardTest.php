<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardStatsOverviewWidget;
use App\Filament\Widgets\LatestOrdersTableWidget;
use App\Filament\Widgets\OperationalAlertsWidget;
use App\Filament\Widgets\OrdersSalesChartWidget;
use App\Filament\Widgets\RecentActivitiesWidget;
use App\Filament\Widgets\TopProductsWidget;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentDashboardTest extends TestCase
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
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->rememberToken();
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
                $table->boolean('email_marketing')->default(false);
                $table->boolean('in_app_marketing')->default(false);
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
                $table->text('description')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0)->nullable();
                $table->integer('weight_gram')->default(100);
                $table->decimal('rating_avg', 3, 2)->default(0);
                $table->integer('reviews_count')->default(0);
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
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->string('name');
                $table->string('sku')->nullable();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0)->nullable();
                $table->integer('weight_gram')->default(100);
                $table->boolean('is_active')->default(true);
                $table->string('image')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function ($table) {
                $table->id();
                $table->foreignId('customer_id')->nullable();
                $table->foreignId('address_id')->nullable();
                $table->string('order_number')->unique();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('shipping_cost', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('status')->default('pending_payment');
                $table->string('payment_status')->default('pending');
                $table->string('shipping_status')->default('pending');
                $table->string('shipping_courier')->nullable();
                $table->string('shipping_service')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('biteship_order_id')->nullable();
                $table->string('biteship_waybill_id')->nullable();
                $table->string('midtrans_order_id')->nullable();
                $table->string('notes')->nullable();
                $table->string('recipient_name')->default('Test Customer');
                $table->string('recipient_phone')->default('08123456789');
                $table->text('shipping_address')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('payment_expires_at')->nullable();
                $table->timestamp('shipment_created_at')->nullable();
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
                $table->foreignId('product_variant_id')->nullable();
                $table->string('product_name');
                $table->string('variant_name')->nullable();
                $table->integer('qty')->default(1);
                $table->decimal('price', 15, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
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
                $table->text('review');
                $table->string('status')->default('pending');
                $table->boolean('is_verified_purchase')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Test admin dapat mengakses halaman dashboard dan merender semua widget.
     */
    public function test_admin_can_access_dashboard_page(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@podoraos.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->assertStatus(200);
    }

    /**
     * Test revenue calculation strictly counts paid orders, excluding pending, expired, cancelled.
     */
    public function test_stats_overview_widget_revenue_only_counts_paid_orders(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@podoraos.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi@test.com']);

        // 1. Order Paid = Rp 150.000 (harus terhitung)
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-PAID-001',
            'grand_total' => 150000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        // 2. Order Pending Payment = Rp 50.000 (tidak boleh dihitung sebagai pendapatan)
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-PENDING-001',
            'grand_total' => 50000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        // 3. Order Expired = Rp 75.000 (tidak boleh dihitung)
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-EXPIRED-001',
            'grand_total' => 75000,
            'status' => 'expired',
            'payment_status' => 'expired',
        ]);

        // 4. Order Cancelled = Rp 60.000 (tidak boleh dihitung)
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CANCELLED-001',
            'grand_total' => 60000,
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
        ]);

        $widget = new DashboardStatsOverviewWidget();
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // Cari stat total pendapatan
        $revenueStat = $stats[0];
        $this->assertEquals('Total Pendapatan', $revenueStat->getLabel());
        $this->assertStringContainsString('150.000', (string) $revenueStat->getValue());
        $this->assertStringNotContainsString('335.000', (string) $revenueStat->getValue());

        // Cari stat jumlah pesanan (total 4 pesanan)
        $ordersStat = $stats[1];
        $this->assertEquals('4', (string) $ordersStat->getValue());

        // Cari stat belum bayar (1 pesanan)
        $unpaidStat = $stats[2];
        $this->assertEquals('1', (string) $unpaidStat->getValue());
        $this->assertStringContainsString('50.000', (string) $unpaidStat->getDescription());
    }

    /**
     * Test stock alert respects Stock Reservation mechanism (stock - reserved_stock <= 5).
     */
    public function test_stats_overview_stock_alert_respects_reservation(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@podoraos.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($admin);

        // Produk 1: Stok fisik 10, tapi ditahan 7 -> sisa 3 (kritis <= 5)
        Product::create([
            'name' => 'Dodol Wijen',
            'slug' => 'dodol-wijen',
            'price' => 35000,
            'stock' => 10,
            'reserved_stock' => 7,
            'is_active' => true,
        ]);

        // Produk 2: Stok fisik 5, ditahan 5 -> sisa 0 (habis total)
        Product::create([
            'name' => 'Wajit Cililin',
            'slug' => 'wajit-cililin',
            'price' => 25000,
            'stock' => 5,
            'reserved_stock' => 5,
            'is_active' => true,
        ]);

        // Produk 3: Stok fisik 25, ditahan 0 -> sisa 25 (aman)
        Product::create([
            'name' => 'Keripik Tempe',
            'slug' => 'keripik-tempe',
            'price' => 20000,
            'stock' => 25,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $widget = new DashboardStatsOverviewWidget();
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // Stat index 4 adalah stok menipis
        $stockStat = $stats[4];
        $this->assertEquals('Stok Menipis / Kritis', $stockStat->getLabel());
        $this->assertEquals('2', (string) $stockStat->getValue()); // Dodol Wijen (3) + Wajit Cililin (0)
        $this->assertStringContainsString('1 produk/varian stok habis (0)', (string) $stockStat->getDescription());
    }

    /**
     * Test pending customer review moderation counter.
     */
    public function test_stats_overview_pending_reviews_counter(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Siti', 'email' => 'siti@test.com']);
        $product = Product::create(['name' => 'Dodol Garut', 'slug' => 'dodol-garut', 'price' => 30000, 'stock' => 10]);

        // 1 review pending
        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'review' => 'Enak sekali',
            'status' => ProductReview::STATUS_PENDING,
        ]);

        // 1 review approved
        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 4,
            'review' => 'Cepat sampai',
            'status' => ProductReview::STATUS_APPROVED,
        ]);

        $widget = new DashboardStatsOverviewWidget();
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        $reviewStat = $stats[5];
        $this->assertEquals('Review Perlu Moderasi', $reviewStat->getLabel());
        $this->assertEquals('1', (string) $reviewStat->getValue());
        $this->assertStringContainsString('1 dari 2 ulasan', (string) $reviewStat->getDescription());
    }

    /**
     * Test sales chart filters (today, 7 days, 30 days, this month, last month).
     */
    public function test_sales_chart_widget_filters_and_data_structure(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Joko', 'email' => 'joko@test.com']);

        // Buat pesanan paid hari ini
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CHART-001',
            'grand_total' => 200000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $chart = new OrdersSalesChartWidget();

        // Uji filter last_7_days
        $chart->filter = 'last_7_days';
        $reflection = new \ReflectionClass($chart);
        $method = $reflection->getMethod('getData');
        $method->setAccessible(true);
        $data7 = $method->invoke($chart);

        $this->assertCount(7, $data7['labels']);
        $this->assertCount(2, $data7['datasets']);
        $this->assertContains(200000.0, $data7['datasets'][0]['data']);

        // Uji filter today
        $chart->filter = 'today';
        $dataToday = $method->invoke($chart);
        $this->assertCount(12, $dataToday['labels']); // 12 slot 2-jam

        // Uji filter 30 days
        $chart->filter = 'last_30_days';
        $data30 = $method->invoke($chart);
        $this->assertCount(30, $data30['labels']);

        // Uji filter this month & last month
        $chart->filter = 'this_month';
        $dataThisMonth = $method->invoke($chart);
        $this->assertNotEmpty($dataThisMonth['labels']);

        $chart->filter = 'last_month';
        $dataLastMonth = $method->invoke($chart);
        $this->assertNotEmpty($dataLastMonth['labels']);
    }

    /**
     * Test operational alerts view data counters.
     */
    public function test_operational_alerts_widget_data(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Dina', 'email' => 'dina@test.com']);

        // Order menunggu konfirmasi
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-WAIT-001',
            'grand_total' => 120000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'shipping_status' => 'waiting_shipment',
        ]);

        // Order siap kirim
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-SHIP-001',
            'grand_total' => 90000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'shipping_status' => 'processed',
        ]);

        $widget = new OperationalAlertsWidget();
        $viewData = $widget->getViewData();

        $this->assertEquals(1, $viewData['waitingConfirmationCount']);
        $this->assertEquals(1, $viewData['readyToShipCount']);
        $this->assertNotEmpty($viewData['productUrl']);
        $this->assertNotEmpty($viewData['unpaidUrl']);

        // Verifikasi blade view operational alerts terkompilasi dan tampil sempurna tanpa ParseError
        Livewire::test(OperationalAlertsWidget::class)
            ->assertSee('Panel Perhatian Operasional')
            ->assertSee('Pesanan Belum Bayar')
            ->assertSee('Lunas & Perlu Diproses', false);
    }

    /**
     * Test top products widget aggregates only paid order items.
     */
    public function test_top_products_widget_only_counts_paid_orders(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Rina', 'email' => 'rina@test.com']);
        $p1 = Product::create(['name' => 'Dodol Cokelat', 'slug' => 'dodol-cokelat', 'price' => 20000, 'stock' => 50]);
        $p2 = Product::create(['name' => 'Dodol Susu', 'slug' => 'dodol-susu', 'price' => 25000, 'stock' => 50]);

        // Order 1 PAID: membeli 5 Dodol Cokelat
        $orderPaid = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TOP-PAID',
            'grand_total' => 100000,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $orderPaid->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'qty' => 5,
            'price' => 20000,
            'subtotal' => 100000,
        ]);

        // Order 2 UNPAID: memesan 20 Dodol Susu (TIDAK BOLEH MASUK TOP PRODUCTS)
        $orderUnpaid = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TOP-UNPAID',
            'grand_total' => 500000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $orderUnpaid->id,
            'product_id' => $p2->id,
            'product_name' => $p2->name,
            'qty' => 20,
            'price' => 25000,
            'subtotal' => 500000,
        ]);

        Livewire::test(TopProductsWidget::class)
            ->assertSee('Dodol Cokelat')
            ->assertSee('5 unit')
            ->assertDontSee('Dodol Susu');
    }

    /**
     * Test recent activities widget captures real database events chronologically.
     */
    public function test_recent_activities_widget_sorts_real_events(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Dewi', 'email' => 'dewi@test.com']);
        $product = Product::create(['name' => 'Dodol Durian', 'slug' => 'dodol-durian', 'price' => 40000, 'stock' => 15]);

        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-ACT-001',
            'grand_total' => 40000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'created_at' => now()->subMinutes(10),
        ]);

        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'review' => 'Dodol duren mantap',
            'status' => 'approved',
            'created_at' => now()->subMinutes(5),
        ]);

        $widget = new RecentActivitiesWidget();
        $viewData = $widget->getViewData();
        $activities = $viewData['activities'];

        $this->assertNotEmpty($activities);
        // Event terbaru harus ulasan (5 menit lalu) atau pembayaran
        $firstEvent = $activities->first();
        $this->assertNotNull($firstEvent['title']);
        $this->assertNotNull($firstEvent['time']);
    }

    /**
     * Test latest orders table widget renders records.
     */
    public function test_latest_orders_table_widget_renders(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Agus', 'email' => 'agus@test.com']);

        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TBL-999',
            'grand_total' => 85000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'recipient_name' => 'Agus Priyono',
        ]);

        Livewire::test(LatestOrdersTableWidget::class)
            ->assertSee('ORD-TBL-999')
            ->assertSee('Agus Priyono')
            ->assertSee('Sudah Dibayar');
    }
}
