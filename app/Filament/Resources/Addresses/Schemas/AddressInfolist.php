<?php

namespace App\Filament\Resources\Addresses\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AddressInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Customer'),

                        TextEntry::make('label')
                            ->label('Address Label')
                            ->badge()
                            ->placeholder('-'),

                        IconEntry::make('is_default')
                            ->label('Default Address')
                            ->boolean(),
                    ])
                    ->columns(3),

                Section::make('Recipient')
                    ->schema([
                        TextEntry::make('recipient_name')
                            ->label('Recipient Name'),

                        TextEntry::make('recipient_phone')
                            ->label('Recipient Phone'),
                    ])
                    ->columns(2),

                Section::make('Location')
                    ->schema([
                        TextEntry::make('province')
                            ->label('Province')
                            ->placeholder('-'),

                        TextEntry::make('city')
                            ->label('City / Regency')
                            ->placeholder('-'),

                        TextEntry::make('district')
                            ->label('District')
                            ->placeholder('-'),

                        TextEntry::make('subdistrict')
                            ->label('Subdistrict / Village')
                            ->placeholder('-'),

                        TextEntry::make('postal_code')
                            ->label('Postal Code')
                            ->placeholder('-'),

                        TextEntry::make('full_address')
                            ->label('Full Address')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('System Information')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
