<?php

namespace App\Filament\Resources\Vouchers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Riwayat Penggunaan Voucher';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.email')
                    ->label('Email Pelanggan')
                    ->searchable(),

                TextColumn::make('order.order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('discount_amount')
                    ->label('Nominal Diskon')
                    ->money('IDR', locale: 'id_ID')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Waktu Digunakan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ]);
    }
}
