<?php

namespace App\Filament\Resources\Addresses;

use App\Filament\Resources\Addresses\Pages\CreateAddress;
use App\Filament\Resources\Addresses\Pages\EditAddress;
use App\Filament\Resources\Addresses\Pages\ListAddresses;
use App\Filament\Resources\Addresses\Pages\ViewAddress;
use App\Filament\Resources\Addresses\Schemas\AddressForm;
use App\Filament\Resources\Addresses\Schemas\AddressInfolist;
use App\Filament\Resources\Addresses\Tables\AddressesTable;
use App\Models\Address;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AddressResource extends Resource
{
    protected static ?string $model = Address::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;

    protected static ?string $recordTitleAttribute = 'recipient_name';
    protected static string | UnitEnum | null $navigationGroup = 'Master';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Alamat';

    protected static ?string $modelLabel = 'Alamat';

    protected static ?string $pluralModelLabel = 'Alamat';

    public static function form(Schema $schema): Schema
    {
        return AddressForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AddressInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AddressesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddresses::route('/'),
            'create' => CreateAddress::route('/create'),
            'view' => ViewAddress::route('/{record}'),
            'edit' => EditAddress::route('/{record}/edit'),
        ];
    }
}
