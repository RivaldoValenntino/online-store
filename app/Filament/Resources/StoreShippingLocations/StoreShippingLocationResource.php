<?php

namespace App\Filament\Resources\StoreShippingLocations;

use App\Filament\Resources\StoreShippingLocations\Pages\CreateStoreShippingLocation;
use App\Filament\Resources\StoreShippingLocations\Pages\EditStoreShippingLocation;
use App\Filament\Resources\StoreShippingLocations\Pages\ListStoreShippingLocations;
use App\Filament\Resources\StoreShippingLocations\Schemas\StoreShippingLocationForm;
use App\Filament\Resources\StoreShippingLocations\Tables\StoreShippingLocationsTable;
use App\Models\StoreShippingLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StoreShippingLocationResource extends Resource
{
    protected static ?string $model = StoreShippingLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan Toko';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Lokasi Pengiriman';

    protected static ?string $modelLabel = 'Lokasi Pengiriman';

    protected static ?string $pluralModelLabel = 'Lokasi Pengiriman';

    public static function form(Schema $schema): Schema
    {
        return StoreShippingLocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreShippingLocationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreShippingLocations::route('/'),
            'create' => CreateStoreShippingLocation::route('/create'),
            'edit' => EditStoreShippingLocation::route('/{record}/edit'),
        ];
    }
}
