<?php

namespace App\Filament\Resources\StoreShippingLocations\Pages;

use App\Filament\Resources\StoreShippingLocations\StoreShippingLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreShippingLocations extends ListRecords
{
    protected static string $resource = StoreShippingLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Lokasi Pengiriman'),
        ];
    }
}
