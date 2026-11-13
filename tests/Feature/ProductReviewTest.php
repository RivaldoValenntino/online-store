<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewImage;
use App\Models\ProductReviewReply;
use App\Models\User;
use App\Notifications\ProductReviewReplyNotification;
use App\Services\ProductReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    protected ProductReviewService $reviewService;

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
        $this->reviewService = app(ProductReviewService::class);
    }

    protected function createDatabaseTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
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
                $table->boolean('unsubscribed_from_promos')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0);
                $table->integer('weight_gram')->default(100);
                $table->decimal('rating_avg', 3, 2)->default(0.00);
                $table->unsignedInteger('reviews_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
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
                $table->string('shipping_status')->default('delivered');
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
                $table->decimal('price', 15, 2)->default(0);
                $table->integer('qty')->default(1);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->foreignId('customer_id');
                $table->foreignId('order_id');
                $table->foreignId('order_item_id')->unique();
                $table->tinyInteger('rating')->unsigned();
                $table->text('review');
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

        if (! Schema::hasTable('product_review_replies')) {
            Schema::create('product_review_replies', function ($table) {
                $table->id();
                $table->foreignId('product_review_id')->unique();
                $table->foreignId('user_id');
                $table->text('reply');
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
    }

    protected function createCustomer(string $name = 'Budi Santoso', string $email = 'budi@example.com'): Customer
    {
        return Customer::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    protected function createProduct(string $name = 'Dodol Garut Asli', string $slug = 'dodol-garut-asli'): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => $slug,
            'price' => 35000,
            'stock' => 100,
            'weight_gram' => 250,
            'is_active' => true,
        ]);
    }

    protected function createCompletedOrder(Customer $customer, Product $product): array
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-' . uniqid(),
            'subtotal' => 35000,
            'shipping_cost' => 10000,
            'discount' => 0,
            'grand_total' => 45000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'shipping_status' => 'delivered',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 35000,
            'qty' => 1,
            'subtotal' => 35000,
        ]);

        return [$order, $orderItem];
    }

    /** 1. User yang belum beli tidak bisa submit ulasan */
    public function test_user_who_did_not_purchase_cannot_submit_review(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct();

        // Customer has no order item, try to pass random order_item_id (99999)
        $this->expectException(AuthorizationException::class);

        $this->reviewService->createReview($customer, 99999, [
            'rating' => 5,
            'review' => 'Produk sangat enak dan manisnya pas!',
        ]);
    }

    /** 2. User yang beli tapi belum selesai/delivered tidak bisa submit ulasan */
    public function test_user_with_uncompleted_order_cannot_submit_review(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct();

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-' . uniqid(),
            'grand_total' => 45000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'shipping_status' => 'pending',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 35000,
            'qty' => 1,
            'subtotal' => 35000,
        ]);

        $this->assertFalse($this->reviewService->canCustomerReviewOrderItem($customer, $orderItem));

        $this->expectException(AuthorizationException::class);
        $this->reviewService->createReview($customer, $orderItem, [
            'rating' => 5,
            'review' => 'Mencoba ulas sebelum bayar dan selesai',
        ]);
    }

    /** 3. User hanya bisa ulas produk yang ada di ordernya */
    public function test_user_can_only_review_product_in_their_order(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $orderItem] = $this->createCompletedOrder($customer, $product);

        $this->assertTrue($this->reviewService->canCustomerReviewOrderItem($customer, $orderItem));

        $review = $this->reviewService->createReview($customer, $orderItem, [
            'rating' => 5,
            'review' => 'Rasa dodolnya juara dan sangat legit!',
        ]);

        $this->assertInstanceOf(ProductReview::class, $review);
        $this->assertEquals($product->id, $review->product_id);
        $this->assertEquals($customer->id, $review->customer_id);
        $this->assertEquals($order->id, $review->order_id);
        $this->assertTrue((bool) $review->is_verified_purchase);
    }

    /** 4. User tidak bisa ulas order milik orang lain */
    public function test_user_cannot_review_another_customers_order(): void
    {
        $customerA = $this->createCustomer('Customer A', 'customerA@example.com');
        $customerB = $this->createCustomer('Customer B', 'customerB@example.com');
        $product = $this->createProduct();

        [$orderB, $orderItemB] = $this->createCompletedOrder($customerB, $product);

        $this->assertFalse($this->reviewService->canCustomerReviewOrderItem($customerA, $orderItemB));

        $this->expectException(AuthorizationException::class);
        $this->reviewService->createReview($customerA, $orderItemB, [
            'rating' => 5,
            'review' => 'Mencoba membajak review pembeli lain',
        ]);
    }

    /** 5. User tidak bisa ulas dua kali untuk item yang sama */
    public function test_duplicate_review_for_same_order_item_is_rejected(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $orderItem] = $this->createCompletedOrder($customer, $product);

        $this->reviewService->createReview($customer, $orderItem, [
            'rating' => 5,
            'review' => 'Ulasan pertama berhasil dibuat.',
        ]);

        $this->assertFalse($this->reviewService->canCustomerReviewOrderItem($customer, $orderItem));

        $this->expectException(AuthorizationException::class);
        $this->reviewService->createReview($customer, $orderItem, [
            'rating' => 4,
            'review' => 'Ulasan kedua seharusnya ditolak.',
        ]);
    }

    /** 6. Rating di luar 1-5 ditolak */
    public function test_rating_outside_1_to_5_is_rejected(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $orderItem] = $this->createCompletedOrder($customer, $product);

        try {
            $this->reviewService->createReview($customer, $orderItem, [
                'rating' => 6,
                'review' => 'Rating 6 tidak valid',
            ]);
            $this->fail('Expected ValidationException was not thrown for rating 6');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rating', $e->errors());
        }

        try {
            $this->reviewService->createReview($customer, $orderItem, [
                'rating' => 0,
                'review' => 'Rating 0 tidak valid',
            ]);
            $this->fail('Expected ValidationException was not thrown for rating 0');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rating', $e->errors());
        }
    }

    /** 7. User tidak bisa memanipulasi status approved atau is_verified_purchase dari input */
    public function test_user_cannot_manipulate_status_or_verified_purchase_flag(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $orderItem] = $this->createCompletedOrder($customer, $product);

        // Client attempts to inject status => rejected and is_verified_purchase => false
        $review = $this->reviewService->createReview($customer, $orderItem, [
            'rating' => 5,
            'review' => 'Produk bagus!',
            'status' => 'rejected',
            'is_verified_purchase' => false,
        ]);

        // Client manipulation is ignored
        $this->assertEquals(ProductReview::STATUS_APPROVED, $review->fresh()->status);
        $this->assertTrue((bool) $review->fresh()->is_verified_purchase);
    }

    /** 8. Rating average dan reviews_count hanya menghitung ulasan yang approved */
    public function test_rating_average_and_reviews_count_only_calculate_approved_reviews(): void
    {
        $customer1 = $this->createCustomer('Cust 1', 'cust1@example.com');
        $customer2 = $this->createCustomer('Cust 2', 'cust2@example.com');
        $customer3 = $this->createCustomer('Cust 3', 'cust3@example.com');
        $product = $this->createProduct();

        [$order1, $item1] = $this->createCompletedOrder($customer1, $product);
        [$order2, $item2] = $this->createCompletedOrder($customer2, $product);
        [$order3, $item3] = $this->createCompletedOrder($customer3, $product);

        $r1 = $this->reviewService->createReview($customer1, $item1, [
            'rating' => 5,
            'review' => 'Bintang 5 mantap!',
            '_internal_status' => ProductReview::STATUS_PENDING,
        ]);

        $r2 = $this->reviewService->createReview($customer2, $item2, [
            'rating' => 3,
            'review' => 'Bintang 3 biasa saja.',
            '_internal_status' => ProductReview::STATUS_PENDING,
        ]);

        $r3 = $this->reviewService->createReview($customer3, $item3, [
            'rating' => 1,
            'review' => 'Bintang 1 tidak suka.',
            '_internal_status' => ProductReview::STATUS_PENDING,
        ]);

        // Initially all are pending -> count = 0, avg = 0
        $product->refresh();
        $this->assertEquals(0, $product->reviews_count);
        $this->assertEquals(0.00, (float) $product->rating_avg);

        // Approve r1 (5) and r2 (3) -> average should be (5+3)/2 = 4.00, count = 2
        $this->reviewService->approveReview($r1);
        $this->reviewService->approveReview($r2);

        $product->refresh();
        $this->assertEquals(2, $product->reviews_count);
        $this->assertEquals(4.00, (float) $product->rating_avg);

        // Reject r3 -> still count = 2, avg = 4.00
        $this->reviewService->rejectReview($r3);
        $product->refresh();
        $this->assertEquals(2, $product->reviews_count);
        $this->assertEquals(4.00, (float) $product->rating_avg);
    }

    /** 9. Admin bisa approve, reject, dan reply ulasan */
    public function test_admin_can_approve_reject_and_reply_to_review(): void
    {
        Notification::fake();

        $admin = User::create([
            'name' => 'Admin Toko',
            'email' => 'admin@podoraos.com',
            'password' => bcrypt('secret123'),
        ]);

        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $item] = $this->createCompletedOrder($customer, $product);

        $review = $this->reviewService->createReview($customer, $item, [
            'rating' => 5,
            'review' => 'Dodolnya enak sekali, pengiriman kilat!',
            '_internal_status' => ProductReview::STATUS_PENDING,
        ]);

        // Admin approves
        $this->reviewService->approveReview($review);
        $this->assertEquals(ProductReview::STATUS_APPROVED, $review->fresh()->status);

        // Admin replies
        $reply = $this->reviewService->saveAdminReply($admin, $review, 'Terima kasih banyak atas ulasannya kak! Selamat menikmati.');
        $this->assertInstanceOf(ProductReviewReply::class, $reply);
        $this->assertEquals('Terima kasih banyak atas ulasannya kak! Selamat menikmati.', $reply->reply);
        $this->assertEquals($admin->id, $reply->user_id);
    }

    /** 10. Reply admin muncul di model dan notifikasi terkirim ke customer */
    public function test_admin_reply_displayed_and_sends_in_app_notification(): void
    {
        $admin = User::create([
            'name' => 'Admin Podo Raos',
            'email' => 'admin2@podoraos.com',
            'password' => bcrypt('secret123'),
        ]);

        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $item] = $this->createCompletedOrder($customer, $product);

        $review = $this->reviewService->createReview($customer, $item, [
            'rating' => 5,
            'review' => 'Sangat recommended!',
        ]);

        $reply = $this->reviewService->saveAdminReply($admin, $review, 'Terima kasih sudah berbelanja di toko kami.');

        // Verify relationship and store display name
        $this->assertEquals('Podo Raos', $reply->store_display_name);
        $this->assertNotNull($review->fresh()->reply);

        // Verify in-app database notification exists in customer notifications
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
            'type' => ProductReviewReplyNotification::class,
        ]);
    }

    /** 11. Customer masking */
    public function test_customer_name_masking(): void
    {
        $customer = $this->createCustomer('Budi Santoso');
        $product = $this->createProduct();
        [$order, $item] = $this->createCompletedOrder($customer, $product);

        $review = $this->reviewService->createReview($customer, $item, [
            'rating' => 5,
            'review' => 'Rasa autentik.',
        ]);

        // Customer masked name should mask middle characters
        $this->assertEquals('B***o', $review->customer_masked_name);
    }

    /** 12. Upload foto ulasan tervalidasi (maksimal 5, format gambar) */
    public function test_photo_upload_validation_max_5_and_image_format(): void
    {
        Storage::fake('public');

        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $item] = $this->createCompletedOrder($customer, $product);

        // Test with 3 valid images
        $photos = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.png'),
            UploadedFile::fake()->image('photo3.webp'),
        ];

        $review = $this->reviewService->createReview($customer, $item, [
            'rating' => 5,
            'review' => 'Ulasan lengkap dengan foto',
        ], $photos);

        $this->assertCount(3, $review->images);

        // Test exceeding 5 images
        [$order2, $item2] = $this->createCompletedOrder($customer, $product);
        $tooManyPhotos = [
            UploadedFile::fake()->image('1.jpg'),
            UploadedFile::fake()->image('2.jpg'),
            UploadedFile::fake()->image('3.jpg'),
            UploadedFile::fake()->image('4.jpg'),
            UploadedFile::fake()->image('5.jpg'),
            UploadedFile::fake()->image('6.jpg'),
        ];

        $this->expectException(ValidationException::class);
        $this->reviewService->createReview($customer, $item2, [
            'rating' => 5,
            'review' => 'Ulasan dengan 6 foto',
        ], $tooManyPhotos);
    }

    /** 13. Updating dan deleting review recalculates rating */
    public function test_updating_and_deleting_review_recalculates_rating(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct();
        [$order, $item] = $this->createCompletedOrder($customer, $product);

        $review = $this->reviewService->createReview($customer, $item, [
            'rating' => 5,
            'review' => 'Awalnya kasih bintang 5',
        ]);

        $product->refresh();
        $this->assertEquals(5.00, (float) $product->rating_avg);
        $this->assertEquals(1, $product->reviews_count);

        // Customer edits review to 4 stars
        $this->reviewService->updateReview($customer, $review, [
            'rating' => 4,
            'review' => 'Diedit jadi bintang 4 karena kemasan agak penyok',
        ]);

        $product->refresh();
        $this->assertEquals(4.00, (float) $product->rating_avg);
        $this->assertEquals(1, $product->reviews_count);

        // Customer deletes review
        $this->reviewService->deleteReview($customer, $review);

        $product->refresh();
        $this->assertEquals(0.00, (float) $product->rating_avg);
        $this->assertEquals(0, $product->reviews_count);
    }
}
