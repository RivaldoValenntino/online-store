<?php

namespace App\Filament\Resources\StoreShippingLocations\Pages;

use App\Filament\Resources\StoreShippingLocations\StoreShippingLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreShippingLocation extends CreateRecord
{
    protected static string $resource = StoreShippingLocationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
