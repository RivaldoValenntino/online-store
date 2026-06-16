<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Banner Content')
                    ->schema([
                        TextInput::make('badge')
                            ->label('Badge')
                            ->placeholder('Produk Lokal')
                            ->maxLength(100),

                        TextInput::make('title')
                            ->label('Title')
                            ->placeholder('Belanja produk UMKM favoritmu')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('subtitle')
                            ->label('Subtitle')
                            ->placeholder('Fresh, homemade, dan siap dikirim langsung ke rumah.')
                            ->maxLength(255),

                        FileUpload::make('image')
                            ->label('Banner Image')
                            ->image()
                            ->disk('public')
                            ->directory('banners')
                            ->visibility('public')
                            ->imagePreviewHeight('180')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Button')
                    ->schema([
                        TextInput::make('button_text')
                            ->label('Button Text')
                            ->placeholder('Belanja sekarang')
                            ->maxLength(100),

                        TextInput::make('button_url')
                            ->label('Button URL')
                            ->placeholder('/products')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Settings')
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),

                        DateTimePicker::make('starts_at')
                            ->label('Mulai Tayang')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d M Y H:i')
                            ->placeholder('Pilih tanggal mulai')
                            ->helperText('Kosongkan jika banner langsung aktif.'),

                        DateTimePicker::make('ends_at')
                            ->label('Selesai Tayang')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d M Y H:i')
                            ->placeholder('Pilih tanggal selesai')
                            ->afterOrEqual('starts_at')
                            ->helperText('Kosongkan jika banner tidak punya batas waktu.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
