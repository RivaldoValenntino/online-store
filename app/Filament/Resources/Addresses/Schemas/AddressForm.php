<?php

namespace App\Filament\Resources\Addresses\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class AddressForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('label')
                            ->label('Address Label')
                            ->placeholder('Rumah / Kantor / Gudang')
                            ->maxLength(100),

                        Toggle::make('is_default')
                            ->label('Default Address')
                            ->default(false),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Recipient')
                    ->schema([
                        TextInput::make('recipient_name')
                            ->label('Recipient Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('recipient_phone')
                            ->label('Recipient Phone')
                            ->tel()
                            ->required()
                            ->maxLength(30),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Location')
                    ->schema([
                        Select::make('province_id')
                            ->label('Province')
                            ->options(fn() => Province::query()
                                ->orderBy('name')
                                ->pluck('name', 'code')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $province = $state
                                    ? Province::query()->where('code', $state)->first()
                                    : null;

                                $set('province', $province?->name);

                                $set('city_id', null);
                                $set('city', null);

                                $set('district_id', null);
                                $set('district', null);

                                $set('subdistrict_id', null);
                                $set('subdistrict', null);

                                $set('postal_code', null);
                            }),

                        Select::make('city_id')
                            ->label('City / Regency')
                            ->options(function (Get $get) {
                                $provinceCode = $get('province_id');

                                if (! $provinceCode) {
                                    return [];
                                }

                                return City::query()
                                    ->where('province_code', $provinceCode)
                                    ->orderBy('name')
                                    ->pluck('name', 'code')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $city = $state
                                    ? City::query()->where('code', $state)->first()
                                    : null;

                                $set('city', $city?->name);

                                $set('district_id', null);
                                $set('district', null);

                                $set('subdistrict_id', null);
                                $set('subdistrict', null);

                                $set('postal_code', null);
                            }),

                        Select::make('district_id')
                            ->label('District')
                            ->options(function (Get $get) {
                                $cityCode = $get('city_id');

                                if (! $cityCode) {
                                    return [];
                                }

                                return District::query()
                                    ->where('city_code', $cityCode)
                                    ->orderBy('name')
                                    ->pluck('name', 'code')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $district = $state
                                    ? District::query()->where('code', $state)->first()
                                    : null;

                                $set('district', $district?->name);

                                $set('subdistrict_id', null);
                                $set('subdistrict', null);

                                $set('postal_code', null);
                            }),

                        Select::make('subdistrict_id')
                            ->label('Subdistrict / Village')
                            ->options(function (Get $get) {
                                $districtCode = $get('district_id');

                                if (! $districtCode) {
                                    return [];
                                }

                                return Village::query()
                                    ->where('district_code', $districtCode)
                                    ->orderBy('name')
                                    ->pluck('name', 'code')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $village = $state
                                    ? Village::query()->where('code', $state)->first()
                                    : null;

                                $set('subdistrict', $village?->name);

                                if ($village && isset($village->postal_code)) {
                                    $set('postal_code', $village->postal_code);
                                }
                            }),

                        TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(20),

                        Textarea::make('full_address')
                            ->label('Full Address')
                            ->placeholder('Nama jalan, nomor rumah, RT/RW, patokan, dll.')
                            ->required()
                            ->columnSpanFull(),

                        Hidden::make('province'),
                        Hidden::make('city'),
                        Hidden::make('district'),
                        Hidden::make('subdistrict'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
