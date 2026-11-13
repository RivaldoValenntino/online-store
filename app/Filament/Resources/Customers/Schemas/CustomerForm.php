<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(30),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->helperText('Kosongkan jika tidak ingin mengubah password.'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),

                        DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Preferensi Notifikasi & Marketing')
                    ->schema([
                        Toggle::make('email_marketing')
                            ->label('Langganan Email Marketing (Newsletter & Promo Blast)')
                            ->default(true)
                            ->helperText('Jika dinonaktifkan, pelanggan tidak akan menerima email promo.'),

                        Toggle::make('in_app_marketing')
                            ->label('Notifikasi Promosi In-App (Lonceng Notifikasi)')
                            ->default(true)
                            ->helperText('Jika dinonaktifkan, pelanggan tidak akan menerima notifikasi lonceng promo.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
