<?php

namespace App\Filament\Resources\Addresses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Label')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('recipient_name')
                    ->label('Recipient')
                    ->searchable(),

                TextColumn::make('recipient_phone')
                    ->label('Phone')
                    ->searchable(),

                TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('district')
                    ->label('District')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('subdistrict')
                    ->label('Village')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('province')
                    ->label('Province')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('postal_code')
                    ->label('Postal Code')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
