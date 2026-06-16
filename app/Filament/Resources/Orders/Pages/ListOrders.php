<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(Order::query()->count()),

            'unpaid' => Tab::make('Belum Bayar')
                ->badge(
                    Order::query()
                        ->where('status', 'pending_payment')
                        ->where('payment_status', 'pending')
                        ->count()
                )
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('status', 'pending_payment')
                    ->where('payment_status', 'pending')),

            'waiting_confirmation' => Tab::make('Menunggu Konfirmasi')
                ->badge(
                    Order::query()
                        ->where('status', 'confirmed')
                        ->where('payment_status', 'paid')
                        ->whereIn('shipping_status', ['pending', 'waiting_shipment'])
                        ->count()
                )
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('status', 'confirmed')
                    ->where('payment_status', 'paid')
                    ->whereIn('shipping_status', ['pending', 'waiting_shipment'])),

            'processing' => Tab::make('Diproses')
                ->badge(
                    Order::query()
                        ->where('status', 'processing')
                        ->where('payment_status', 'paid')
                        ->where('shipping_status', 'processed')
                        ->count()
                )
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('status', 'processing')
                    ->where('payment_status', 'paid')
                    ->where('shipping_status', 'processed')),

            'shipped' => Tab::make('Dalam Pengiriman')
                ->badge(
                    Order::query()
                        ->where('status', 'processing')
                        ->where('payment_status', 'paid')
                        ->where('shipping_status', 'shipped')
                        ->count()
                )
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('status', 'processing')
                    ->where('payment_status', 'paid')
                    ->where('shipping_status', 'shipped')),

            'delivered' => Tab::make('Terkirim')
                ->badge(
                    Order::query()
                        ->where('status', 'completed')
                        ->where('payment_status', 'paid')
                        ->where('shipping_status', 'delivered')
                        ->count()
                )
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('status', 'completed')
                    ->where('payment_status', 'paid')
                    ->where('shipping_status', 'delivered')),

            'cancelled' => Tab::make('Dibatalkan')
                ->badge(
                    Order::query()
                        ->where(function (Builder $query) {
                            $query->whereIn('status', ['cancelled', 'expired'])
                                ->orWhereIn('payment_status', ['expired', 'cancelled', 'denied', 'failed'])
                                ->orWhereIn('shipping_status', ['cancelled', 'returned', 'failed']);
                        })
                        ->count()
                )
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where(function (Builder $query) {
                        $query->whereIn('status', ['cancelled', 'expired'])
                            ->orWhereIn('payment_status', ['expired', 'cancelled', 'denied', 'failed'])
                            ->orWhereIn('shipping_status', ['cancelled', 'returned', 'failed']);
                    })),
        ];
    }
}