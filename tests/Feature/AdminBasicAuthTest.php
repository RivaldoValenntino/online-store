<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminBasicAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.admin_basic_auth.enabled' => true,
            'services.admin_basic_auth.username' => 'superadmin',
            'services.admin_basic_auth.password' => 'Secret123!',
            'services.admin_basic_auth.realm' => 'Podo Raos Admin Area',
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

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function ($table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->foreignId('customer_id')->nullable();
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('status')->default('pending_payment');
                $table->string('payment_status')->default('pending');
                $table->string('shipping_status')->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function ($table) {
                $table->id();
                $table->foreignId('order_id');
                $table->foreignId('product_id')->nullable();
                $table->string('product_name')->nullable();
                $table->integer('qty')->default(1);
                $table->decimal('price', 15, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * 1. Membuka /admin tanpa kredensial menghasilkan HTTP 401 dan header WWW-Authenticate.
     */
    public function test_accessing_admin_without_credentials_returns_401(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate');
        $this->assertStringContainsString('Basic realm="Podo Raos Admin Area"', $response->headers->get('WWW-Authenticate'));
    }

    /**
     * 2. Username atau password salah ditolak dengan HTTP 401.
     */
    public function test_wrong_credentials_are_rejected_with_401(): void
    {
        // Username salah
        $responseWrongUser = $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('wronguser:Secret123!'),
        ])->get('/admin/login');

        $responseWrongUser->assertStatus(401);

        // Password salah
        $responseWrongPass = $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('superadmin:wrongpass'),
        ])->get('/admin/login');

        $responseWrongPass->assertStatus(401);
    }

    /**
     * 3. Kredensial benar dapat membuka halaman login Filament.
     */
    public function test_correct_credentials_open_filament_login_page(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('superadmin:Secret123!'),
        ])->get('/admin/login');

        $response->assertStatus(200);
    }

    /**
     * 4. Login admin Filament tetap berfungsi setelah Basic Auth berhasil.
     */
    public function test_filament_admin_user_can_authenticate_and_access_dashboard(): void
    {
        $admin = User::create([
            'name' => 'Admin Podo Raos',
            'email' => 'admin@podoraos.com',
            'password' => bcrypt('AdminPassword123!'),
        ]);

        // Mengakses dashboard dengan Basic Auth dan session admin yang sudah login
        $response = $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('superadmin:Secret123!'),
        ])->actingAs($admin)->get('/admin');

        $response->assertStatus(200);
    }

    /**
     * 5. Route kustom admin (seperti invoice) juga terproteksi Basic Auth.
     */
    public function test_custom_admin_routes_are_protected(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-INV-001',
            'grand_total' => 150000,
        ]);

        $admin = User::create([
            'name' => 'Admin Invoice',
            'email' => 'invoice@podoraos.com',
            'password' => bcrypt('AdminPassword123!'),
        ]);

        // Tanpa basic auth -> 401
        $this->actingAs($admin)->get("/admin/orders/{$order->id}/invoice")
            ->assertStatus(401);

        // Dengan basic auth benar -> dapat diproses (stream pdf)
        $this->actingAs($admin)->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('superadmin:Secret123!'),
        ])->get("/admin/orders/{$order->id}/invoice")
            ->assertStatus(200);
    }

    /**
     * 6. Website publik tetap bisa dibuka tanpa autentikasi tambahan.
     */
    public function test_public_storefront_does_not_require_basic_auth(): void
    {
        // Halaman katalog produk publik
        $response = $this->get('/products');
        $this->assertNotEquals(401, $response->getStatusCode());

        // Halaman keranjang publik
        $cartResponse = $this->get('/cart');
        $this->assertNotEquals(401, $cartResponse->getStatusCode());
    }

    /**
     * 7. Webhook Midtrans dan Biteship tidak terproteksi Basic Auth.
     */
    public function test_webhooks_are_not_protected_by_admin_basic_auth(): void
    {
        // Webhook Midtrans POST
        $midtransResponse = $this->postJson('/webhooks/midtrans', [
            'order_id' => 'ORD-TEST-001',
            'status_code' => '200',
            'transaction_status' => 'settlement',
        ]);
        // Tidak boleh 401 Basic Auth (bisa 200/400/404 bergantung payload Midtrans)
        $this->assertNotEquals(401, $midtransResponse->getStatusCode());

        // Webhook Biteship POST
        $biteshipResponse = $this->postJson('/webhooks/biteship', [
            'event' => 'order.status',
        ]);
        $this->assertNotEquals(401, $biteshipResponse->getStatusCode());
    }

    /**
     * 8. Fitur dapat dinonaktifkan via environment variable ADMIN_BASIC_AUTH_ENABLED=false.
     */
    public function test_feature_can_be_disabled_via_config(): void
    {
        config(['services.admin_basic_auth.enabled' => false]);

        // Tanpa kredensial, sekarang dapat langsung membuka /admin/login tanpa HTTP 401
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    /**
     * 9. HTTPS enforcement pada environment production.
     */
    public function test_https_is_enforced_in_production(): void
    {
        $this->app['env'] = 'production';

        // Akses via HTTP biasa (non-secure) pada production harus redirect ke HTTPS
        $response = $this->get('http://localhost/admin/login');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }

    /**
     * 10. Sesi yang telah lulus Basic Auth tidak memerlukan re-autentikasi pada request berikutnya (mencegah glitch Livewire).
     */
    public function test_authenticated_session_does_not_require_authorization_header(): void
    {
        $admin = User::create([
            'name' => 'Admin Livewire',
            'email' => 'livewire@podoraos.com',
            'password' => bcrypt('password'),
        ]);

        // Request pertama dengan basic auth -> berhasil
        $firstResponse = $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('superadmin:Secret123!'),
        ])->actingAs($admin)->get('/admin');

        $firstResponse->assertStatus(200);

        // Request kedua tanpa header Authorization (seperti request AJAX / Livewire dari browser)
        // Harus tetap berhasil 200 karena session hash valid
        $secondResponse = $this->actingAs($admin)->get('/admin');
        $secondResponse->assertStatus(200);
    }
}
