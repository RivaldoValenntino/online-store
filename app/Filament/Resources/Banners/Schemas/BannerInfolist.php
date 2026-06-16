<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Banner Preview')
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Banner Image')
                            ->disk('public')
                            ->height(220)
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Banner Content')
                    ->schema([
                        TextEntry::make('badge')
                            ->label('Badge')
                            ->badge()
                            ->placeholder('-'),

                        TextEntry::make('title')
                            ->label('Title')
                            ->weight('bold'),

                        TextEntry::make('subtitle')
                            ->label('Subtitle')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Button')
                    ->schema([
                        TextEntry::make('button_text')
                            ->label('Button Text')
                            ->placeholder('-'),

                        TextEntry::make('button_url')
                            ->label('Button URL')
                            ->placeholder('-')
                            ->url(fn($record) => $record->button_url ?: null)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),

                Section::make('Display Settings')
                    ->schema([
                        TextEntry::make('sort_order')
                            ->label('Sort Order')
                            ->numeric(),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),

                        TextEntry::make('starts_at')
                            ->label('Mulai Tayang')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Langsung aktif'),

                        TextEntry::make('ends_at')
                            ->label('Selesai Tayang')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Tidak dibatasi'),
                    ])
                    ->columns(2),

                Section::make('System Information')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
