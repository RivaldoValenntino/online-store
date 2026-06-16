<?php

use App\Http\Controllers\BiteshipWebhookController;
use App\Http\Controllers\MidtransWebhookController;
use App\Models\Order;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::store.home')
    ->name('store.home');


Route::get('/admin/orders/{order}/invoice', function (Order $order) {
    return view('admin.orders.invoice', compact('order'));
})->middleware(['auth'])->name('admin.orders.invoice');

Route::livewire('/products', 'pages::store.products')
    ->name('store.products');

Route::livewire('/products/{slug}', 'pages::store.product-detail')
    ->name('store.product.detail');

Route::livewire('/categories', 'pages::store.categories')
    ->name('store.categories');

Route::livewire('/categories/{slug}', 'pages::store.category-products')
    ->name('store.category.products');

Route::livewire('/cart', 'pages::store.cart')
    ->name('store.cart');

Route::livewire('/checkout', 'pages::store.checkout')
    ->name('store.checkout')
    ->middleware(['auth:customer', 'verified']);

Route::livewire('/account', 'pages::store.account')
    ->name('store.account');

Route::livewire('/account/addresses', 'pages::store.addresses')
    ->name('store.addresses')
    ->middleware('auth:customer');

Route::livewire('/account/addresses/create', 'pages::store.address-form')
    ->name('store.addresses.create')
    ->middleware('auth:customer');

Route::livewire('/account/addresses/{id}/edit', 'pages::store.address-form')
    ->name('store.addresses.edit')
    ->middleware('auth:customer');
/**
 * Customer Auth
 */
Route::livewire('/login', 'pages::auth.login')
    ->name('customer.login')
    ->middleware('guest:customer');

Route::livewire('/register', 'pages::auth.register')
    ->name('customer.register')
    ->middleware('guest:customer');

Route::post('/logout', function (Request $request) {
    Auth::guard('customer')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()
        ->route('store.home')
        ->with('flash.type', 'success')
        ->with('flash.title', 'Berhasil logout')
        ->with('flash.message', 'Kamu sudah keluar dari akun.');
})->name('customer.logout');
/**
 * Email Verification
 */
Route::livewire('/email/verify', 'pages::auth.verify-email')
    ->middleware('auth:customer')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect()
        ->route('store.home')
        ->with('flash.type', 'success')
        ->with('flash.title', 'Email berhasil diverifikasi')
        ->with('flash.message', 'Selamat! Akun kamu sudah aktif dan bisa digunakan untuk checkout.');
})->middleware(['auth:customer', 'signed', 'throttle:6,1'])
    ->name('verification.verify');


Route::post('/email/verification-notification', function (Request $request) {
    $request->user('customer')->sendEmailVerificationNotification();

    return back()->with('status', 'verification-link-sent');
})->middleware(['auth:customer', 'throttle:6,1'])
    ->name('verification.send');

/**
 * Forgot Password
 */
Route::livewire('/forgot-password', 'pages::auth.forgot-password')
    ->name('password.request')
    ->middleware('guest:customer');

Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')
    ->name('password.reset')
    ->middleware('guest:customer');

Route::post('/webhooks/midtrans', MidtransWebhookController::class)
    ->name('webhooks.midtrans');

Route::get('/account/orders/{order}/shipping-label', function (Order $order) {
    abort_unless($order->customer_id === Auth::guard('customer')->id(), 403);

    $order->load(['items']);

    return view('store.orders.shipping-label', compact('order'));
})
    ->name('store.orders.shipping-label')
    ->middleware('auth:customer');

Route::livewire('/account/orders/{order}', 'pages::store.order-detail')
    ->name('store.orders.show')
    ->middleware('auth:customer');

Route::livewire('/account/orders', 'pages::store.orders')
    ->name('store.orders')
    ->middleware('auth:customer');

Route::get('/payment/midtrans/return', function () {
    $orderNumber = request('order_id');

    $order = Order::query()
        ->where('order_number', $orderNumber)
        ->first();

    if ($order) {
        return redirect()->route('store.orders.show', $order);
    }

    return redirect()->route('store.orders');
})->name('payment.midtrans.return');

Route::post('/webhooks/biteship', BiteshipWebhookController::class)
    ->name('webhooks.biteship');