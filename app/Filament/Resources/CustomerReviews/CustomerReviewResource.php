<?php

namespace App\Filament\Resources\CustomerReviews;

use App\Filament\Resources\CustomerReviews\Pages\ListCustomerReviews;
use App\Filament\Resources\CustomerReviews\Pages\ViewCustomerReview;
use App\Filament\Resources\CustomerReviews\Tables\CustomerReviewsTable;
use App\Models\ProductReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CustomerReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Customer Reviews';

    protected static ?string $modelLabel = 'Customer Review';

    protected static ?string $pluralModelLabel = 'Customer Reviews';

    public static function table(Table $table): Table
    {
        return CustomerReviewsTable::configure($table);
    }

    public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return \App\Filament\Resources\CustomerReviews\Schemas\CustomerReviewInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerReviews::route('/'),
            'view' => ViewCustomerReview::route('/{record}'),
        ];
    }
}
