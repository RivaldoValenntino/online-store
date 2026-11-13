<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerReviews\CustomerReviewResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * Hitung statistik utama dengan data aktual dari database.
     */
    protected function getStats(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // 1. Total Pendapatan: HANYA transaksi yang pembayarannya terverifikasi (payment_status = 'paid')
        $totalRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->sum('grand_total');

        $thisMonthRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('grand_total');

        $lastMonthRevenue = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('grand_total');

        // Sparkline 7 hari pendapatan
        $revenueChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = $now->copy()->subDays($i)->startOfDay();
            $dayEnd = $now->copy()->subDays($i)->endOfDay();
            $revenueChart[] = (float) Order::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('grand_total');
        }

        // 2. Jumlah Pesanan
        $totalOrders = Order::query()->count();
        $paidOrders = Order::query()->where('payment_status', 'paid')->count();
        $thisMonthOrders = Order::query()->where('created_at', '>=', $startOfMonth)->count();

        // Sparkline 7 hari pesanan
        $ordersChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = $now->copy()->subDays($i)->startOfDay();
            $dayEnd = $now->copy()->subDays($i)->endOfDay();
            $ordersChart[] = (int) Order::query()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();
        }

        // 3. Pesanan Menunggu Pembayaran
        $unpaidQuery = Order::query()
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending');
        $unpaidCount = (int) $unpaidQuery->count();
        $unpaidAmount = (float) $unpaidQuery->sum('grand_total');

        // 4. Pesanan Sudah Dibayar & Perlu Diproses/Kirim
        $needProcessingCount = (int) Order::query()
            ->where('payment_status', 'paid')
            ->whereIn('status', ['confirmed', 'processing'])
            ->whereIn('shipping_status', ['pending', 'waiting_shipment', 'processed'])
            ->count();

        // 5. Produk Stok Menipis / Kritis (Stok fisik - reserved <= 5)
        $standaloneLowStock = Product::query()
            ->whereDoesntHave('variants')
            ->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 5')
            ->count();

        $variantLowStock = ProductVariant::query()
            ->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 5')
            ->count();

        $totalLowStock = (int) ($standaloneLowStock + $variantLowStock);

        $standaloneOutOfStock = Product::query()
            ->whereDoesntHave('variants')
            ->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 0')
            ->count();

        $variantOutOfStock = ProductVariant::query()
            ->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 0')
            ->count();

        $totalOutOfStock = (int) ($standaloneOutOfStock + $variantOutOfStock);

        // 6. Review Menunggu Moderasi
        $pendingReviewsCount = (int) ProductReview::query()
            ->where('status', ProductReview::STATUS_PENDING)
            ->count();

        $totalReviews = (int) ProductReview::query()->count();

        // Hitung persentase tren pendapatan bulan ini vs bulan lalu
        $revenueDiffText = 'Bulan ini: Rp ' . number_format($thisMonthRevenue, 0, ',', '.');
        if ($lastMonthRevenue > 0) {
            $growth = round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
            $sign = $growth >= 0 ? '+' : '';
            $revenueDiffText .= " ({$sign}{$growth}% vs bln lalu)";
        }

        return [
            Stat::make('Total Pendapatan', 'Rp ' . number_format($totalRevenue, 0, ',', '.'))
                ->description($revenueDiffText)
                ->descriptionIcon($thisMonthRevenue >= $lastMonthRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('success')
                ->chart($revenueChart),

            Stat::make('Jumlah Pesanan', number_format($totalOrders, 0, ',', '.'))
                ->description("{$paidOrders} terbayar | {$thisMonthOrders} pesanan bulan ini")
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->url(OrderResource::getUrl('index'))
                ->chart($ordersChart),

            Stat::make('Menunggu Pembayaran', number_format($unpaidCount, 0, ',', '.'))
                ->description('Rp ' . number_format($unpaidAmount, 0, ',', '.') . ' total nilai tertahan')
                ->descriptionIcon('heroicon-m-clock')
                ->color($unpaidCount > 0 ? 'warning' : 'gray')
                ->url(OrderResource::getUrl('index', ['activeTab' => 'unpaid'])),

            Stat::make('Perlu Diproses & Kirim', number_format($needProcessingCount, 0, ',', '.'))
                ->description($needProcessingCount > 0 ? 'Pesanan lunas siap disiapkan' : 'Semua pesanan telah diproses')
                ->descriptionIcon('heroicon-m-truck')
                ->color($needProcessingCount > 0 ? 'info' : 'success')
                ->url(OrderResource::getUrl('index', ['activeTab' => 'waiting_confirmation'])),

            Stat::make('Stok Menipis / Kritis', number_format($totalLowStock, 0, ',', '.'))
                ->description($totalOutOfStock > 0 ? "{$totalOutOfStock} produk/varian stok habis (0)" : 'Stok tersedia <= 5 unit')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($totalOutOfStock > 0 ? 'danger' : ($totalLowStock > 0 ? 'warning' : 'success'))
                ->url(ProductResource::getUrl('index', ['tableFilters' => ['low_stock' => ['isActive' => true]]])),

            Stat::make('Review Perlu Moderasi', number_format($pendingReviewsCount, 0, ',', '.'))
                ->description($pendingReviewsCount > 0 ? "{$pendingReviewsCount} dari {$totalReviews} ulasan menunggu" : 'Semua ulasan telah dimoderasi')
                ->descriptionIcon('heroicon-m-star')
                ->color($pendingReviewsCount > 0 ? 'warning' : 'gray')
                ->url(CustomerReviewResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'pending']]])),
        ];
    }
}
