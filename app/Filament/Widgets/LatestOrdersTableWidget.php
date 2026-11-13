<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrdersTableWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pesanan Terbaru')
            ->description('Daftar transaksi pesanan pelanggan yang masuk')
            ->query(
                Order::query()
                    ->with(['customer', 'items'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('recipient_name')
                    ->label('Pelanggan / Penerima')
                    ->searchable()
                    ->description(fn(Order $record): string => $record->customer?->email ?? $record->recipient_phone ?? '-'),

                TextColumn::make('created_at')
                    ->label('Tanggal Pesanan')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total Transaksi')
                    ->money('IDR', locale: 'id')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Status Bayar')
                    ->badge()
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'pending' => 'Menunggu Bayar',
                        'paid' => 'Sudah Dibayar',
                        'expired' => 'Kedaluwarsa',
                        'cancelled' => 'Dibatalkan',
                        'denied' => 'Ditolak',
                        'failed' => 'Gagal',
                        default => ucfirst($state ?? '-'),
                    })
                    ->color(fn(?string $state) => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'expired', 'cancelled', 'denied', 'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('shipping_status')
                    ->label('Status Pengiriman')
                    ->badge()
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'pending' => 'Pending',
                        'waiting_shipment' => 'Menunggu Kirim',
                        'processed' => 'Diproses',
                        'shipped' => 'Dalam Pengiriman',
                        'delivered' => 'Terkirim',
                        'cancelled' => 'Dibatalkan',
                        'returned' => 'Dikembalikan',
                        'failed' => 'Gagal',
                        default => ucfirst(str_replace('_', ' ', $state ?? '-')),
                    })
                    ->color(fn(?string $state) => match ($state) {
                        'pending' => 'gray',
                        'waiting_shipment' => 'warning',
                        'processed' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled', 'returned', 'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Action::make('preview_invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn(Order $record) => route('admin.orders.invoice', $record))
                    ->openUrlInNewTab(),

                Action::make('view_order')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn(Order $record) => OrderResource::getUrl('index', [
                        'tableSearch' => $record->order_number,
                    ])),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Belum ada transaksi pesanan')
            ->emptyStateDescription('Pesanan yang dibuat oleh pelanggan akan otomatis tampil di sini.');
    }
}
