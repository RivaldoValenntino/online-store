<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Models\ProductOptionValue;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Product')
                    ->schema([
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $set('slug', Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->readOnly(),

                        RichEditor::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->extraAttributes([
                                'style' => 'min-height: 300px;',
                            ]),

                        TextInput::make('price')
                            ->label('Base Price')
                            ->helperText('Dipakai kalau produk tidak punya varian.')
                            ->prefix('Rp')
                            ->currencyMask(thousandSeparator: ',', decimalSeparator: '.', precision: 2)
                            ->required()
                            ->default(0),

                        TextInput::make('stock')
                            ->label('Base Stock')
                            ->helperText('Dipakai kalau produk tidak punya varian.')
                            ->numeric()
                            ->required()
                            ->default(0),

                        TextInput::make('weight_gram')
                            ->label('Base Weight')
                            ->helperText('Dipakai kalau produk tidak punya varian.')
                            ->numeric()
                            ->suffix('gram')
                            ->required()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Product Images')
                    ->schema([
                        Repeater::make('images')
                            ->label('Product Images')
                            ->relationship('images')
                            ->schema([
                                FileUpload::make('image')
                                    ->label('Image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('products')
                                    ->visibility('public')
                                    ->imagePreviewHeight('150')
                                    ->maxSize(2048)
                                    ->deleteUploadedFileUsing(function (?string $file) {
                                        if ($file) {
                                            Storage::disk('public')->delete($file);
                                        }
                                    })
                                    ->required(),

                                Toggle::make('is_primary')
                                    ->label('Primary Image')
                                    ->default(false),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->defaultItems(0)
                            ->addActionLabel('Add image'),
                    ])
                    ->columnSpanFull(),

                Section::make('Product Options')
                    ->description('Contoh: Tekstur = Keras/Lembek, Rasa = Original/Kakao')
                    ->schema([
                        Repeater::make('options')
                            ->label('Options')
                            ->relationship('options')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Option Name')
                                    ->placeholder('Contoh: Tekstur / Rasa / Ukuran / Warna')
                                    ->required(),

                                TextInput::make('sort_order')
                                    ->label('Sort')
                                    ->numeric()
                                    ->default(0),

                                Repeater::make('values')
                                    ->label('Option Values')
                                    ->relationship('values')
                                    ->schema([
                                        TextInput::make('value')
                                            ->label('Value')
                                            ->placeholder('Contoh: Keras / Lembek / Original / Kakao')
                                            ->required(),

                                        TextInput::make('sort_order')
                                            ->label('Sort')
                                            ->numeric()
                                            ->default(0),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add value')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->addActionLabel('Add option'),
                    ])
                    ->columnSpanFull(),

                Section::make('Product Variants')
                    ->description('Tambahkan kombinasi varian. Contoh: Dodol - Lembek - Original.')
                    ->schema([
                        Repeater::make('variants')
                            ->label('Variants')
                            ->relationship('variants')
                            ->schema([
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->placeholder('DODOL-LEMBEK-ORIGINAL')
                                    ->unique(
                                        table: 'product_variants',
                                        column: 'sku',
                                        ignoreRecord: true
                                    ),

                                TextInput::make('name')
                                    ->label('Variant Name')
                                    ->placeholder('Dodol - Lembek - Original')
                                    ->required(),

                                Select::make('optionValues')
                                    ->label('Option Combination')
                                    ->relationship('optionValues', 'value')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function (ProductOptionValue $record) {
                                        return ($record->option?->name ?? '-') . ': ' . $record->value;
                                    })
                                    ->helperText('Pilih 1 value dari setiap option. Contoh: Tekstur: Lembek + Rasa: Original.')
                                    ->columnSpanFull(),

                                TextInput::make('price')
                                    ->label('Price')
                                    ->prefix('Rp')
                                    ->currencyMask(thousandSeparator: ',', decimalSeparator: '.', precision: 2)
                                    ->required()
                                    ->default(0),

                                TextInput::make('stock')
                                    ->numeric()
                                    ->required()
                                    ->default(0),

                                TextInput::make('weight_gram')
                                    ->label('Weight')
                                    ->numeric()
                                    ->suffix('gram')
                                    ->required()
                                    ->default(0),
                                FileUpload::make('image')
                                    ->label('Image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('product-variants')
                                    ->visibility('public')
                                    ->imagePreviewHeight('120')
                                    ->maxSize(2048)
                                    ->deleteUploadedFileUsing(function (?string $file) {
                                        if ($file) {
                                            Storage::disk('public')->delete($file);
                                        }
                                    }),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add variant'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
