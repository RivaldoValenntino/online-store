<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardStatsOverviewWidget;
use App\Filament\Widgets\LatestOrdersTableWidget;
use App\Filament\Widgets\OperationalAlertsWidget;
use App\Filament\Widgets\OrdersSalesChartWidget;
use App\Filament\Widgets\RecentActivitiesWidget;
use App\Filament\Widgets\TopProductsWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Pusat Operasional & Monitoring Toko';

    public function getSubheading(): string|Htmlable|null
    {
        $appName = (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));
        return "Pantau performa pendapatan, volume pesanan, stok kritis, dan moderasi ulasan {$appName} secara real-time.";
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            DashboardStatsOverviewWidget::class,
            OrdersSalesChartWidget::class,
            OperationalAlertsWidget::class,
            LatestOrdersTableWidget::class,
            TopProductsWidget::class,
            RecentActivitiesWidget::class,
        ];
    }
}
