<?php

namespace App\Filament\Resources\StoreShippingLocations\Tables;

use App\Models\StoreShippingLocation;
use App\Services\BiteshipService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoreShippingLocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Lokasi')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact_name')
                    ->label('Kontak Pengirim')
                    ->description(fn (StoreShippingLocation $record) => $record->contact_phone)
                    ->searchable(),

                TextColumn::make('biteship_area_label')
                    ->label('Wilayah Biteship')
                    ->limit(35)
                    ->tooltip(fn (StoreShippingLocation $record) => $record->biteship_area_label)
                    ->searchable(),

                TextColumn::make('postal_code')
                    ->label('Kode Pos')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_default')
                    ->label('Utama')
                    ->boolean()
                    ->trueIcon('heroicon-s-check-badge')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('coordinates')
                    ->label('Koordinat')
                    ->state(function (StoreShippingLocation $record) {
                        if ($record->latitude && $record->longitude) {
                            return "{$record->latitude}, {$record->longitude}";
                        }
                        return '-';
                    })
                    ->color('gray')
                    ->size('xs'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('makeDefault')
                    ->label('Jadikan Default')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Jadikan Lokasi Pengiriman Default?')
                    ->modalDescription('Lokasi ini akan menjadi alamat asal pengiriman utama yang digunakan saat checkout pelanggan.')
                    ->visible(fn (StoreShippingLocation $record) => ! $record->is_default && $record->is_active)
                    ->action(function (StoreShippingLocation $record) {
                        $record->makeDefault();
                        Notification::make()
                            ->title('Lokasi Default Diperbarui')
                            ->body("\"{$record->name}\" sekarang menjadi lokasi pengiriman default.")
                            ->success()
                            ->send();
                    }),

                Action::make('testRates')
                    ->label('Tes Ongkir')
                    ->icon('heroicon-m-truck')
                    ->color('info')
                    ->modalHeading(fn (StoreShippingLocation $record) => "Tes Ongkir dari {$record->name}")
                    ->modalDescription('Simulasikan cek ongkir Biteship dari lokasi ini ke tujuan pengujian tanpa membuat pesanan sungguhan.')
                    ->form([
                        Select::make('destination_area_id')
                            ->label('Tujuan Pengiriman (Kecamatan / Kota / Kode Pos)')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                try {
                                    $areas = app(BiteshipService::class)->searchAreas($search);
                                    return collect($areas)->mapWithKeys(function ($area) {
                                        return [$area['id'] => app(BiteshipService::class)->areaLabel($area)];
                                    })->all();
                                } catch (\Throwable $e) {
                                    return [];
                                }
                            })
                            ->getOptionLabelUsing(function ($value) {
                                if (blank($value)) {
                                    return null;
                                }

                                $loc = StoreShippingLocation::where('biteship_area_id', $value)->first();
                                if ($loc?->biteship_area_label) {
                                    return $loc->biteship_area_label;
                                }

                                try {
                                    $areas = app(BiteshipService::class)->searchAreas($value);
                                    $area = collect($areas)->firstWhere('id', $value) ?? ($areas[0] ?? null);
                                    if ($area) {
                                        return app(BiteshipService::class)->areaLabel($area);
                                    }
                                } catch (\Throwable $e) {
                                    // ignore API errors
                                }

                                return (string) $value;
                            }),

                        TextInput::make('weight_gram')
                            ->label('Berat Paket Pengujian (Gram)')
                            ->numeric()
                            ->default(1000)
                            ->required()
                            ->suffix('gram'),

                        TextInput::make('couriers')
                            ->label('Pilihan Kurir')
                            ->default('jne,jnt,sicepat,anteraja')
                            ->helperText('Pisahkan dengan koma (contoh: jne,jnt,sicepat,anteraja,gojek,grab)'),
                    ])
                    ->action(function (StoreShippingLocation $record, array $data) {
                        try {
                            $payload = [
                                'origin_area_id' => $record->biteship_area_id,
                                'destination_area_id' => $data['destination_area_id'],
                                'couriers' => $data['couriers'] ?: 'jne,jnt,sicepat,anteraja',
                                'items' => [
                                    [
                                        'name' => 'Paket Pengujian',
                                        'description' => 'Test Item',
                                        'value' => 50000,
                                        'quantity' => 1,
                                        'weight' => (int) $data['weight_gram'],
                                    ],
                                ],
                            ];

                            if ($record->latitude && $record->longitude) {
                                $payload['origin_latitude'] = (float) $record->latitude;
                                $payload['origin_longitude'] = (float) $record->longitude;
                            }

                            $rates = app(BiteshipService::class)->rates($payload);

                            if (empty($rates)) {
                                Notification::make()
                                    ->title('Tidak Ada Opsi Pengiriman')
                                    ->body('Biteship tidak menemukan layanan kurir yang tersedia untuk rute ini.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $summary = collect($rates)->map(function ($r) {
                                return "• {$r['name']} ({$r['service']}): Rp " . number_format($r['cost'], 0, ',', '.') . " [ETD: {$r['etd']}]";
                            })->implode("\n");

                            Notification::make()
                                ->title('Berhasil Mengambil Ongkir Biteship (' . count($rates) . ' Opsi)')
                                ->body($summary)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal Mengambil Ongkir')
                                ->body('Error Biteship: ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
