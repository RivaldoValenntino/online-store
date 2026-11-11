<?php

namespace App\Filament\Resources\PromoCampaigns\Pages;

use App\Filament\Resources\PromoCampaigns\PromoCampaignResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPromoCampaign extends ViewRecord
{
    protected static string $resource = PromoCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
