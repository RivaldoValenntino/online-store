<?php

namespace App\Filament\Resources\PromoCampaigns\Pages;

use App\Filament\Resources\PromoCampaigns\PromoCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromoCampaigns extends ListRecords
{
    protected static string $resource = PromoCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
