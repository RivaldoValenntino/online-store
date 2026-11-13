<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerReviews\CustomerReviewResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Filament\Widgets\Widget;

class OperationalAlertsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.operational-alerts-widget';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 5,
    ];

    public function getViewData(): array
    {
        // 1. Produk Stok Kritis (stok tersedia <= 5)
        $lowStockProducts = Product::query()
            ->whereDoesntHave('variants')
            ->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 5')
            ->orderByRaw('(stock - COALESCE(reserved_stock, 0)) ASC')
            ->limit(3)
            ->get(['id', 'name', 'stock', 'reserved_stock']);

        $lowStockVariants = ProductVariant::query()
            ->with('product:id,name')
            ->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 5')
            ->orderByRaw('(stock - COALESCE(reserved_stock, 0)) ASC')
            ->limit(3)
            ->get(['id', 'product_id', 'name', 'stock', 'reserved_stock']);

        $totalLowStockCount = (int) (
            Product::query()->whereDoesntHave('variants')->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 5')->count()
            + ProductVariant::query()->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 5')->count()
        );

        $totalOutOfStockCount = (int) (
            Product::query()->whereDoesntHave('variants')->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 0')->count()
            + ProductVariant::query()->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 0')->count()
        );

        // 2. Pesanan Menunggu Pembayaran
        $unpaidOrders = Order::query()
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->latest()
            ->limit(3)
            ->get(['id', 'order_number', 'recipient_name', 'grand_total', 'payment_expires_at', 'created_at']);

        $unpaidCount = (int) Order::query()
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->count();

        $unpaidTotal = (float) Order::query()
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->sum('grand_total');

        // 3. Pesanan Sudah Dibayar tapi Belum Diproses
        $waitingConfirmationOrders = Order::query()
            ->where('payment_status', 'paid')
            ->where('status', 'confirmed')
            ->whereIn('shipping_status', ['pending', 'waiting_shipment'])
            ->latest()
            ->limit(3)
            ->get(['id', 'order_number', 'recipient_name', 'grand_total', 'created_at']);

        $waitingConfirmationCount = (int) Order::query()
            ->where('payment_status', 'paid')
            ->where('status', 'confirmed')
            ->whereIn('shipping_status', ['pending', 'waiting_shipment'])
            ->count();

        // 4. Pesanan Perlu Dikirim / Siap Pickup
        $readyToShipOrders = Order::query()
            ->where('payment_status', 'paid')
            ->where('status', 'processing')
            ->whereIn('shipping_status', ['processed', 'waiting_shipment'])
            ->latest()
            ->limit(3)
            ->get(['id', 'order_number', 'recipient_name', 'shipping_courier', 'shipping_service', 'created_at']);

        $readyToShipCount = (int) Order::query()
            ->where('payment_status', 'paid')
            ->where('status', 'processing')
            ->whereIn('shipping_status', ['processed', 'waiting_shipment'])
            ->count();

        // 5. Review yang Belum Dimoderasi
        $pendingReviews = ProductReview::query()
            ->with(['product:id,name', 'customer:id,name'])
            ->where('status', ProductReview::STATUS_PENDING)
            ->latest()
            ->limit(3)
            ->get();

        $pendingReviewsCount = (int) ProductReview::query()
            ->where('status', ProductReview::STATUS_PENDING)
            ->count();

        return [
            'totalLowStockCount' => $totalLowStockCount,
            'totalOutOfStockCount' => $totalOutOfStockCount,
            'lowStockProducts' => $lowStockProducts,
            'lowStockVariants' => $lowStockVariants,
            'productUrl' => ProductResource::getUrl('index', ['tableFilters' => ['low_stock' => ['isActive' => true]]]),

            'unpaidCount' => $unpaidCount,
            'unpaidTotal' => $unpaidTotal,
            'unpaidOrders' => $unpaidOrders,
            'unpaidUrl' => OrderResource::getUrl('index', ['activeTab' => 'unpaid']),

            'waitingConfirmationCount' => $waitingConfirmationCount,
            'waitingConfirmationOrders' => $waitingConfirmationOrders,
            'waitingConfirmationUrl' => OrderResource::getUrl('index', ['activeTab' => 'waiting_confirmation']),

            'readyToShipCount' => $readyToShipCount,
            'readyToShipOrders' => $readyToShipOrders,
            'readyToShipUrl' => OrderResource::getUrl('index', ['activeTab' => 'processing']),

            'pendingReviewsCount' => $pendingReviewsCount,
            'pendingReviews' => $pendingReviews,
            'pendingReviewsUrl' => CustomerReviewResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'pending']]]),
        ];
    }
}
