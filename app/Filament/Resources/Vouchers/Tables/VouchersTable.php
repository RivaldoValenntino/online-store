<?php

namespace App\Filament\Resources\Vouchers\Tables;

use App\Models\Voucher;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode Voucher')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Kode disalin ke clipboard'),

                TextColumn::make('name')
                    ->label('Nama Promo')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Voucher::types()[$state] ?? $state)
                    ->colors([
                        'primary' => Voucher::TYPE_PERCENTAGE,
                        'success' => Voucher::TYPE_FIXED,
                        'info' => Voucher::TYPE_FREE_SHIPPING,
                        'warning' => Voucher::TYPE_SHIPPING_DISCOUNT,
                    ]),

                TextColumn::make('amount')
                    ->label('Besar Diskon')
                    ->formatStateUsing(fn (Voucher $record) => $record->formattedDiscount()),

                TextColumn::make('total_used')
                    ->label('Terpakai / Kuota')
                    ->formatStateUsing(function (Voucher $record) {
                        $quotaText = is_null($record->total_quota) ? '∞' : $record->total_quota;
                        return "{$record->total_used} / {$quotaText}";
                    })
                    ->badge()
                    ->color(function (Voucher $record) {
                        if (!is_null($record->total_quota) && $record->total_used >= $record->total_quota) {
                            return 'danger';
                        }
                        if (!is_null($record->total_quota) && $record->total_used >= ($record->total_quota * 0.8)) {
                            return 'warning';
                        }
                        return 'success';
                    }),

                TextColumn::make('usages_sum_discount_amount')
                    ->sum('usages', 'discount_amount')
                    ->label('Total Diskon Diberikan')
                    ->money('IDR', locale: 'id_ID')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('expires_at')
                    ->label('Kedaluwarsa')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Tanpa Batas')
                    ->color(fn (Voucher $record) => $record->isExpired() ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis Diskon')
                    ->options(Voucher::types()),

                Filter::make('active_only')
                    ->label('Hanya Voucher Aktif')
                    ->query(fn (Builder $query): Builder => $query->active()),

                Filter::make('expired')
                    ->label('Sudah Kedaluwarsa')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<', now())),

                Filter::make('almost_empty')
                    ->label('Kuota Hampir Habis (>= 80%)')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('total_quota')->whereRaw('total_used >= (total_quota * 0.8)')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
