<?php

namespace App\Filament\Resources\Vouchers\Widgets;

use App\Models\Voucher;
use App\Models\VoucherUsage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VoucherStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalVouchers = Voucher::count();
        $activeVouchers = Voucher::active()->count();
        $totalRedemptions = VoucherUsage::count();
        $totalDiscountGiven = VoucherUsage::sum('discount_amount');

        return [
            Stat::make('Total Voucher', $totalVouchers)
                ->description("{$activeVouchers} voucher aktif saat ini")
                ->icon('heroicon-o-ticket')
                ->color('primary'),

            Stat::make('Total Digunakan', $totalRedemptions)
                ->description('Total transaksi yang memakai voucher')
                ->icon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Total Diskon Diberikan', 'Rp ' . number_format($totalDiscountGiven, 0, ',', '.'))
                ->description('Akumulasi potongan belanja')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
        ];
    }
}
