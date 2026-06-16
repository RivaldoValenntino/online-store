<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->schema([
                        ImageEntry::make('primaryImage.image')
                            ->label('Primary Image')
                            ->disk('public')
                            ->square()
                            ->height(120)
                            ->placeholder('-')
                            ->defaultImageUrl(url('images/image-not-found.jpg')),

                        TextEntry::make('category.name')
                            ->label('Category')
                            ->placeholder('-'),

                        TextEntry::make('name')
                            ->label('Product Name'),

                        TextEntry::make('slug')
                            ->label('Slug'),

                        TextEntry::make('price')
                            ->label('Base Price')
                            ->money('IDR'),

                        TextEntry::make('stock')
                            ->label('Base Stock')
                            ->numeric(),

                        TextEntry::make('weight_gram')
                            ->label('Base Weight')
                            ->suffix(' gram')
                            ->numeric(),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),

                        TextEntry::make('description')
                            ->label('Description')
                            ->html()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Product Images')
                    ->schema([
                        RepeatableEntry::make('images')
                            ->label('')
                            ->schema([
                                ImageEntry::make('image')
                                    ->label('Image')
                                    ->disk('public')
                                    ->square()
                                    ->height(100),

                                IconEntry::make('is_primary')
                                    ->label('Primary')
                                    ->boolean(),

                                TextEntry::make('sort_order')
                                    ->label('Sort')
                                    ->numeric(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Product Options')
                    ->description('Pilihan produk seperti Tekstur, Kemasan, Berat.')
                    ->schema([
                        RepeatableEntry::make('options')
                            ->label('')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Option Name'),

                                TextEntry::make('sort_order')
                                    ->label('Sort')
                                    ->numeric(),

                                RepeatableEntry::make('values')
                                    ->label('Values')
                                    ->schema([
                                        TextEntry::make('value')
                                            ->label('Value'),

                                        TextEntry::make('sort_order')
                                            ->label('Sort')
                                            ->numeric(),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Product Variants')
                    ->description('Varian final yang dipilih customer saat belanja.')
                    ->schema([
                        RepeatableEntry::make('variants')
                            ->label('')
                            ->schema([
                                ImageEntry::make('image')
                                    ->label('Image')
                                    ->disk('public')
                                    ->square()
                                    ->height(80)
                                    ->placeholder('-'),

                                TextEntry::make('sku')
                                    ->label('SKU')
                                    ->badge()
                                    ->placeholder('-'),

                                TextEntry::make('name')
                                    ->label('Variant Name'),

                                TextEntry::make('price')
                                    ->label('Price')
                                    ->money('IDR'),

                                TextEntry::make('stock')
                                    ->label('Stock')
                                    ->numeric(),

                                TextEntry::make('weight_gram')
                                    ->label('Weight')
                                    ->suffix(' gram')
                                    ->numeric(),

                                IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

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
