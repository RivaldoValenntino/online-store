<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class OrdersSalesChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Grafik Penjualan & Pendapatan';

    protected ?string $description = 'Tren pendapatan terverifikasi dan volume transaksi per periode waktu';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 7,
    ];

    protected ?string $maxHeight = '320px';

    public ?string $filter = 'last_7_days';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hari ini',
            'last_7_days' => '7 Hari Terakhir',
            'last_30_days' => '30 Hari Terakhir',
            'this_month' => 'Bulan Ini',
            'last_month' => 'Bulan Lalu',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $filter = $this->filter ?: 'last_7_days';

        $labels = [];
        $revenueData = [];
        $ordersData = [];

        switch ($filter) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();

                // Ambil semua order terbayar hari ini dalam 1 query hemat
                $orders = Order::query()
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->get(['id', 'grand_total', 'created_at']);

                // Siapkan slot per 2 jam (00:00, 02:00, ..., 22:00) agar grafik tidak sesak
                for ($hour = 0; $hour < 24; $hour += 2) {
                    $slotStart = $hour;
                    $slotEnd = $hour + 1;
                    $label = sprintf('%02d:00', $hour);
                    $labels[] = $label;

                    $slotOrders = $orders->filter(function ($order) use ($slotStart, $slotEnd) {
                        $orderHour = (int) $order->created_at->setTimezone('Asia/Jakarta')->format('H');
                        return $orderHour >= $slotStart && $orderHour <= $slotEnd;
                    });

                    $revenueData[] = (float) $slotOrders->sum('grand_total');
                    $ordersData[] = (int) $slotOrders->count();
                }
                break;

            case 'last_30_days':
                $start = $now->copy()->subDays(29)->startOfDay();
                $end = $now->copy()->endOfDay();

                $orders = Order::query()
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->get(['id', 'grand_total', 'created_at']);

                for ($i = 29; $i >= 0; $i--) {
                    $date = $now->copy()->subDays($i);
                    $dateKey = $date->format('Y-m-d');
                    $labels[] = $date->translatedFormat('d M');

                    $dayOrders = $orders->filter(function ($order) use ($dateKey) {
                        return $order->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d') === $dateKey;
                    });

                    $revenueData[] = (float) $dayOrders->sum('grand_total');
                    $ordersData[] = (int) $dayOrders->count();
                }
                break;

            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfDay();
                $totalDays = $now->day;

                $orders = Order::query()
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->get(['id', 'grand_total', 'created_at']);

                for ($day = 1; $day <= $totalDays; $day++) {
                    $date = $now->copy()->startOfMonth()->addDays($day - 1);
                    $dateKey = $date->format('Y-m-d');
                    $labels[] = $date->translatedFormat('d M');

                    $dayOrders = $orders->filter(function ($order) use ($dateKey) {
                        return $order->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d') === $dateKey;
                    });

                    $revenueData[] = (float) $dayOrders->sum('grand_total');
                    $ordersData[] = (int) $dayOrders->count();
                }
                break;

            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $totalDays = $start->daysInMonth;

                $orders = Order::query()
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->get(['id', 'grand_total', 'created_at']);

                for ($day = 1; $day <= $totalDays; $day++) {
                    $date = $start->copy()->addDays($day - 1);
                    $dateKey = $date->format('Y-m-d');
                    $labels[] = $date->translatedFormat('d M');

                    $dayOrders = $orders->filter(function ($order) use ($dateKey) {
                        return $order->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d') === $dateKey;
                    });

                    $revenueData[] = (float) $dayOrders->sum('grand_total');
                    $ordersData[] = (int) $dayOrders->count();
                }
                break;

            case 'last_7_days':
            default:
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();

                $orders = Order::query()
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->get(['id', 'grand_total', 'created_at']);

                for ($i = 6; $i >= 0; $i--) {
                    $date = $now->copy()->subDays($i);
                    $dateKey = $date->format('Y-m-d');
                    $labels[] = $date->translatedFormat('d M');

                    $dayOrders = $orders->filter(function ($order) use ($dateKey) {
                        return $order->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d') === $dateKey;
                    });

                    $revenueData[] = (float) $dayOrders->sum('grand_total');
                    $ordersData[] = (int) $dayOrders->count();
                }
                break;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Pendapatan (Rp)',
                    'data' => $revenueData,
                    'borderColor' => '#10b981', // Emerald
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => 'start',
                    'tension' => 0.35,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Jumlah Pesanan Berbayar',
                    'data' => $ordersData,
                    'borderColor' => '#f59e0b', // Amber
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => false,
                    'tension' => 0.35,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
    {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                labels: { usePointStyle: true, boxWidth: 8 }
            },
            tooltip: {
                enabled: true,
                callbacks: {
                    label: function (context) {
                        const value = context.parsed.y;
                        if (context.datasetIndex === 0) {
                            return context.dataset.label + ': Rp ' + value.toLocaleString('id-ID');
                        }
                        return context.dataset.label + ': ' + value + ' pesanan';
                    }
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: { display: true, text: 'Pendapatan (Rp)' },
                grid: { drawBorder: false },
                ticks: {
                    callback: function (value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: { display: true, text: 'Pesanan' },
                grid: { drawOnChartArea: false },
                ticks: { precision: 0, stepSize: 1 }
            }
        }
    }
    JS);
    }
}
