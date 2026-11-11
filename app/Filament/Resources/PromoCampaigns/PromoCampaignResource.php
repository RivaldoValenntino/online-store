<?php

namespace App\Filament\Resources\PromoCampaigns;

use App\Filament\Resources\PromoCampaigns\Pages\CreatePromoCampaign;
use App\Filament\Resources\PromoCampaigns\Pages\EditPromoCampaign;
use App\Filament\Resources\PromoCampaigns\Pages\ListPromoCampaigns;
use App\Filament\Resources\PromoCampaigns\Pages\ViewPromoCampaign;
use App\Filament\Resources\PromoCampaigns\Schemas\PromoCampaignForm;
use App\Filament\Resources\PromoCampaigns\Tables\PromoCampaignsTable;
use App\Models\PromoCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PromoCampaignResource extends Resource
{
    protected static ?string $model = PromoCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Megaphone;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'Promosi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Campaign Blast';

    protected static ?string $modelLabel = 'Campaign Promo';

    protected static ?string $pluralModelLabel = 'Campaign Blast Promosi';

    public static function form(Schema $schema): Schema
    {
        return PromoCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoCampaignsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoCampaigns::route('/'),
            'create' => CreatePromoCampaign::route('/create'),
            'view' => ViewPromoCampaign::route('/{record}'),
            'edit' => EditPromoCampaign::route('/{record}/edit'),
        ];
    }
}
