<?php

namespace App\Filament\Resources\PromoCampaigns\Pages;

use App\Filament\Resources\PromoCampaigns\PromoCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoCampaign extends EditRecord
{
    protected static string $resource = PromoCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
