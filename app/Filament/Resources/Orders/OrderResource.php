<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Services\BiteshipService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $modelLabel = 'Pesanan';

    protected static ?string $pluralModelLabel = 'Pesanan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            //
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->latest())
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->searchable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pembayaran')
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

                Tables\Columns\TextColumn::make('shipping_status')
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

                Tables\Columns\TextColumn::make('midtrans_order_id')
                    ->label('Payment Gateway')
                    ->formatStateUsing(fn($state) => filled($state) ? 'Midtrans' : '-')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('shipping_courier')
                    ->label('Kurir')
                    ->formatStateUsing(function ($state, Order $record) {
                        if (! $record->shipping_courier && ! $record->shipping_service) {
                            return '-';
                        }

                        return strtoupper((string) $record->shipping_courier) . ' - ' . ($record->shipping_service ?: '-');
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([



                ActionGroup::make([
                    Action::make('preview_invoice')
                        ->label('Preview Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->url(fn(Order $record) => route('admin.orders.invoice', $record))
                        ->openUrlInNewTab(),

                    Action::make('ship_order')
                        ->label('Kirim ke Biteship')
                        ->icon('heroicon-o-truck')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim pesanan ke Biteship?')
                        ->modalDescription('Sistem akan membuat order pengiriman di Biteship dan menyimpan nomor resi jika tersedia.')
                        ->visible(
                            fn(Order $record) =>
                            $record->payment_status === 'paid'
                                && in_array($record->status, ['confirmed', 'processing'], true)
                                && blank($record->biteship_order_id)
                                && ! in_array($record->shipping_status, ['processed', 'shipped', 'delivered', 'cancelled'], true)
                        )
                        ->action(function (Order $record) {
                            if (! $record->address?->biteship_area_id && ! $record->address?->postal_code) {
                                Notification::make()
                                    ->title('Alamat belum valid')
                                    ->body('Alamat belum punya area Biteship atau kode pos.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                $response = app(BiteshipService::class)->createOrder($record);

                                logger()->info('BITESHIP CREATE ORDER RESPONSE', [
                                    'order_id' => $record->id,
                                    'response' => $response,
                                ]);

                                // $record->update([
                                //     'biteship_order_id' => $response['id'] ?? null,
                                //     'biteship_waybill_id' => $response['courier_waybill_id'] ?? null,
                                //     'tracking_number' => $response['courier_tracking_id'] ?? ($response['courier_waybill_id'] ?? null),
                                //     'shipping_label_url' => $response['courier_link'] ?? null,
                                //     'shipment_created_at' => now(),

                                //     // Status lokal
                                //     'status' => 'processing',
                                //     'shipping_status' => 'processed',
                                // ]);
                                $record->update([
                                    'biteship_order_id' => $response['id'] ?? null,
                                    'biteship_waybill_id' => $response['courier_waybill_id'] ?? null,
                                    'tracking_number' => $response['courier_tracking_id'] ?? ($response['courier_waybill_id'] ?? null),
                                    'shipping_label_url' => $response['courier_link'] ?? null,
                                    'shipment_created_at' => now(),

                                    'status' => 'processing',
                                    'shipping_status' => 'processed',
                                ]);

                                Notification::make()
                                    ->title('Pesanan dikirim ke Biteship')
                                    ->body('Order pengiriman berhasil dibuat. Resi akan tersimpan jika sudah tersedia dari kurir.')
                                    ->success()
                                    ->send();
                            } catch (RequestException $e) {
                                $body = $e->response?->json();

                                Notification::make()
                                    ->title('Gagal kirim ke Biteship')
                                    ->body(($body['error'] ?? $e->getMessage()) . (isset($body['code']) ? ' (' . $body['code'] . ')' : ''))
                                    ->danger()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);

                                Notification::make()
                                    ->title('Gagal kirim ke Biteship')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('cancel_order')
                        ->label('Batalkan Pesanan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn(Order $record) =>
                            ! in_array($record->shipping_status, ['delivered', 'cancelled'], true)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Batalkan pesanan ini?')
                        ->modalDescription('Jika pesanan sudah dibuat di Biteship, sistem juga akan mencoba membatalkan order pengirimannya.')
                        ->form([
                            Textarea::make('cancel_reason')
                                ->label('Alasan pembatalan')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function (Order $record, array $data) {
                            try {
                                if (filled($record->biteship_order_id)) {
                                    app(BiteshipService::class)->cancelOrder(
                                        biteshipOrderId: $record->biteship_order_id,
                                        reason: $data['cancel_reason'],
                                    );
                                }

                                $record->update([
                                    'status' => 'cancelled',
                                    'shipping_status' => 'cancelled',
                                    'notes' => trim(($record->notes ? $record->notes . "\n" : '') . 'BATAL: ' . $data['cancel_reason']),
                                ]);

                                Notification::make()
                                    ->title('Pesanan dibatalkan')
                                    ->body(filled($record->biteship_order_id) ? 'Order lokal dan Biteship berhasil dibatalkan.' : 'Order lokal berhasil dibatalkan.')
                                    ->success()
                                    ->send();
                            } catch (RequestException $e) {
                                $body = $e->response?->json();

                                Notification::make()
                                    ->title('Gagal membatalkan pesanan')
                                    ->body(($body['error'] ?? $e->getMessage()) . (isset($body['code']) ? ' (' . $body['code'] . ')' : ''))
                                    ->danger()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);

                                Notification::make()
                                    ->title('Gagal membatalkan pesanan')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Aksi')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size('sm')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada pesanan')
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}