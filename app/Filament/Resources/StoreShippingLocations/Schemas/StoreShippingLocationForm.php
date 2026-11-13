<?php

namespace App\Filament\Resources\StoreShippingLocations\Schemas;

use App\Models\StoreShippingLocation;
use App\Services\BiteshipService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoreShippingLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->components([
                // Section 1: Identitas & Kontak (Full Width di bagian atas)
                Section::make('Identitas & Kontak Lokasi')
                    ->description('Nama lokasi gudang/toko dan kontak PIC pengirim yang akan tertera pada label pengiriman.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextInput::make('name')
                                ->label('Nama Lokasi / Gudang')
                                ->required()
                                ->placeholder('Contoh: Gudang Utama Garut')
                                ->maxLength(255),

                            TextInput::make('contact_name')
                                ->label('Nama Kontak Pengirim')
                                ->required()
                                ->placeholder('Contoh: Admin ' . (config('services.apps.app_name') ?: config('app.name', 'Podo Raos')))
                                ->maxLength(255),

                            TextInput::make('contact_phone')
                                ->label('Nomor Telepon / WA')
                                ->required()
                                ->tel()
                                ->placeholder('Contoh: 08123456789')
                                ->maxLength(30),
                        ]),

                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Toggle::make('is_default')
                                ->label('Jadikan Alamat Asal Default')
                                ->helperText('Lokasi ini akan digunakan secara otomatis pada checkout untuk menghitung ongkos kirim pelanggan.')
                                ->default(fn() => StoreShippingLocation::count() === 0),

                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->helperText('Hanya lokasi aktif yang dapat digunakan untuk proses pengiriman.')
                                ->default(true),
                        ]),
                    ]),

                // Kolom Kiri: Alamat Fisik & Biteship Area
                Section::make('Wilayah Biteship & Alamat Fisik')
                    ->description('Pilih wilayah resmi dari Biteship agar perhitungan ongkos kirim akurat.')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Select::make('biteship_area_id')
                            ->label('Cari Wilayah Resmi Biteship (Kecamatan / Kota / Kode Pos)')
                            ->helperText('Ketik minimal 3 karakter nama kecamatan, kota, atau kode pos.')
                            ->required()
                            ->searchable()
                            ->live()
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
                                return $loc?->biteship_area_label ?: $value;
                            })
                            ->afterStateUpdated(function ($state, $set) {
                                if (blank($state)) {
                                    return;
                                }
                                try {
                                    $areas = app(BiteshipService::class)->searchAreas($state);
                                    $area = collect($areas)->firstWhere('id', $state) ?? ($areas[0] ?? null);
                                    if ($area) {
                                        $label = app(BiteshipService::class)->areaLabel($area);
                                        $set('biteship_area_label', $label);
                                        $set('province', $area['administrative_division_level_1_name'] ?? null);
                                        $set('city', $area['administrative_division_level_2_name'] ?? null);
                                        $set('district', $area['administrative_division_level_3_name'] ?? null);
                                        $set('subdistrict', $area['name'] ?? null);
                                        if (filled($area['postal_code'])) {
                                            $set('postal_code', (string) $area['postal_code']);
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    // Silent fallback
                                }
                            })
                            ->columnSpanFull(),

                        Hidden::make('biteship_area_label'),

                        Textarea::make('address')
                            ->label('Alamat Fisik Lengkap')
                            ->placeholder('Nama jalan, nomor bangunan, patokan, RT/RW...')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('province')->label('Provinsi'),
                            TextInput::make('city')->label('Kota / Kabupaten'),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('district')->label('Kecamatan'),
                            TextInput::make('subdistrict')->label('Kelurahan / Desa'),
                            TextInput::make('postal_code')->label('Kode Pos')->numeric()->maxLength(10),
                        ]),
                    ]),

                // Kolom Kanan: Titik Koordinat & Peta Penjemputan
                Section::make('Koordinat GPS & Catatan Penjemputan Kurir')
                    ->description('Koordinat sangat penting untuk kurir instan / same-day (GoSend, Grab) dan penjemputan paket Biteship.')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('latitude')
                                ->label('Latitude')
                                ->numeric()
                                ->placeholder('-7.227414'),

                            TextInput::make('longitude')
                                ->label('Longitude')
                                ->numeric()
                                ->placeholder('107.908699'),
                        ]),

                        Textarea::make('courier_notes')
                            ->label('Catatan Khusus untuk Kurir Penjemput')
                            ->placeholder('Contoh: Masuk gang samping ruko, gedung cat hijau, penjemputan di pintu samping...')
                            ->rows(2)
                            ->columnSpanFull(),

                        ViewField::make('map_picker')
                            ->view('filament.forms.components.map-picker')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
