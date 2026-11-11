<?php

namespace App\Filament\Resources\Vouchers;

use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Resources\Vouchers\Pages\ViewVoucher;
use App\Filament\Resources\Vouchers\RelationManagers\UsagesRelationManager;
use App\Filament\Resources\Vouchers\Schemas\VoucherForm;
use App\Filament\Resources\Vouchers\Tables\VouchersTable;
use App\Filament\Resources\Vouchers\Widgets\VoucherStatsOverview;
use App\Models\Voucher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Ticket;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|UnitEnum|null $navigationGroup = 'Promosi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Voucher & Promo';

    protected static ?string $modelLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Voucher & Promo';

    public static function form(Schema $schema): Schema
    {
        return VoucherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VouchersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsagesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            VoucherStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
            'view' => ViewVoucher::route('/{record}'),
            'edit' => EditVoucher::route('/{record}/edit'),
        ];
    }
}
