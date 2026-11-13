<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerReviews\CustomerReviewResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class RecentActivitiesWidget extends Widget
{
    protected static ?int $sort = 6;

    protected string $view = 'filament.widgets.recent-activities-widget';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 6,
    ];

    public function getViewData(): array
    {
        $activities = collect();

        // 1. Pesanan Baru Masuk
        $latestOrders = Order::query()
            ->with('customer:id,name,email')
            ->latest('created_at')
            ->limit(4)
            ->get();

        foreach ($latestOrders as $order) {
            $customerName = $order->customer?->name ?: ($order->recipient_name ?: 'Pelanggan');
            $activities->push([
                'type' => 'order_created',
                'title' => "Pesanan Baru #{$order->order_number}",
                'description' => "Oleh {$customerName} sebesar Rp " . number_format($order->grand_total, 0, ',', '.'),
                'time' => $order->created_at,
                'icon' => 'heroicon-o-shopping-bag',
                'color' => 'blue',
                'url' => OrderResource::getUrl('index', ['tableSearch' => $order->order_number]),
            ]);
        }

        // 2. Pembayaran Berhasil Terverifikasi
        $paidOrders = Order::query()
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->limit(4)
            ->get();

        foreach ($paidOrders as $order) {
            $gateway = filled($order->midtrans_order_id) ? 'Midtrans' : 'Gateway';
            $activities->push([
                'type' => 'payment_paid',
                'title' => "Pembayaran Lunas #{$order->order_number}",
                'description' => "Rp " . number_format($order->grand_total, 0, ',', '.') . " terverifikasi ({$gateway})",
                'time' => $order->paid_at,
                'icon' => 'heroicon-o-check-circle',
                'color' => 'emerald',
                'url' => OrderResource::getUrl('index', ['tableSearch' => $order->order_number]),
            ]);
        }

        // 3. Pengiriman / Resi Biteship Dibuat
        $shippedOrders = Order::query()
            ->whereNotNull('shipment_created_at')
            ->latest('shipment_created_at')
            ->limit(3)
            ->get();

        foreach ($shippedOrders as $order) {
            $courier = strtoupper((string) ($order->shipping_courier ?: 'Kurir'));
            $resi = $order->tracking_number ? "Resi: {$order->tracking_number}" : "Pengiriman disiapkan";
            $activities->push([
                'type' => 'shipment_created',
                'title' => "Resi Pengiriman #{$order->order_number}",
                'description' => "{$courier} - {$resi}",
                'time' => $order->shipment_created_at,
                'icon' => 'heroicon-o-truck',
                'color' => 'indigo',
                'url' => OrderResource::getUrl('index', ['tableSearch' => $order->order_number]),
            ]);
        }

        // 4. Ulasan Pelanggan Baru Masuk
        $reviews = ProductReview::query()
            ->with(['product:id,name', 'customer:id,name'])
            ->latest('created_at')
            ->limit(4)
            ->get();

        foreach ($reviews as $review) {
            $reviewer = $review->customer?->name ?: 'Pelanggan';
            $productName = $review->product?->name ?: 'Produk';
            $activities->push([
                'type' => 'review_created',
                'title' => "Ulasan Baru {$review->rating}⭐",
                'description' => "Oleh {$reviewer} untuk {$productName}",
                'time' => $review->created_at,
                'icon' => 'heroicon-o-star',
                'color' => 'amber',
                'url' => CustomerReviewResource::getUrl('index'),
            ]);
        }

        // 5. Produk Baru Ditambahkan
        $products = Product::query()
            ->latest('created_at')
            ->limit(3)
            ->get();

        foreach ($products as $prod) {
            $activities->push([
                'type' => 'product_created',
                'title' => "Katalog Baru: {$prod->name}",
                'description' => "Stok awal {$prod->stock} unit - Rp " . number_format($prod->price, 0, ',', '.'),
                'time' => $prod->created_at,
                'icon' => 'heroicon-o-plus-circle',
                'color' => 'purple',
                'url' => ProductResource::getUrl('index'),
            ]);
        }

        // Urutkan seluruh aktivitas aktual berdasarkan waktu terbaru dan ambil 8 teratas
        $sortedActivities = $activities
            ->filter(fn($act) => !empty($act['time']))
            ->sortByDesc(fn($act) => $act['time'] instanceof Carbon ? $act['time']->timestamp : Carbon::parse($act['time'])->timestamp)
            ->values()
            ->take(8);

        return [
            'activities' => $sortedActivities,
        ];
    }
}
