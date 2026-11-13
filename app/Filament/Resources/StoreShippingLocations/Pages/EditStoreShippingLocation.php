<?php

namespace App\Filament\Resources\StoreShippingLocations\Pages;

use App\Filament\Resources\StoreShippingLocations\StoreShippingLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreShippingLocation extends EditRecord
{
    protected static string $resource = StoreShippingLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
