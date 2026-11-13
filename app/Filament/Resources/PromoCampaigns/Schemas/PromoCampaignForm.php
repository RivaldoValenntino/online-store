<?php

namespace App\Filament\Resources\PromoCampaigns\Schemas;

use App\Models\PromoCampaign;
use App\Models\Voucher;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromoCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pengaturan Campaign')
                    ->schema([
                        TextInput::make('title')
                            ->label('Nama Campaign')
                            ->required()
                            ->placeholder('Contoh: Promo Gajian Idul Fitri')
                            ->maxLength(255),

                        Select::make('voucher_id')
                            ->label('Tautkan Voucher Promo')
                            ->relationship('voucher', 'code')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Pilih voucher yang ingin dipromosikan (opsional).'),

                        CheckboxList::make('channels')
                            ->label('Kanal Distribusi')
                            ->options([
                                PromoCampaign::CHANNEL_BANNER => 'Banner Website',
                                PromoCampaign::CHANNEL_EMAIL => 'Email Blast (Brevo)',
                                PromoCampaign::CHANNEL_IN_APP => 'In-App Notification (Notifikasi Akun)',
                            ])
                            ->default([PromoCampaign::CHANNEL_IN_APP, PromoCampaign::CHANNEL_EMAIL])
                            ->required()
                            ->columns(3),

                        Select::make('target_audience')
                            ->label('Target Penerima')
                            ->options(PromoCampaign::audienceOptions())
                            ->default(PromoCampaign::AUDIENCE_ALL)
                            ->reactive()
                            ->required(),

                        Select::make('target_customer_ids')
                            ->label('Pilih Pelanggan Target')
                            ->multiple()
                            ->searchable()
                            ->options(fn() => \App\Models\Customer::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn($get) => $get('target_audience') === PromoCampaign::AUDIENCE_SPECIFIC)
                            ->required(fn($get) => $get('target_audience') === PromoCampaign::AUDIENCE_SPECIFIC)
                            ->helperText('Pilih akun pelanggan yang secara spesifik akan menerima campaign ini.')
                            ->columnSpanFull(),

                        DateTimePicker::make('scheduled_at')
                            ->label('Jadwal Pengiriman (Opsional)')
                            ->helperText('Kosongkan jika ingin dikirim langsung saat action blast dijalankan.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Konten & Pesan Promosi')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Subject Email / Judul Notifikasi')
                            ->placeholder('Diskon Spesial Menanti Anda! 🎁')
                            ->maxLength(255),

                        Textarea::make('content')
                            ->label('Isi Pesan Promosi')
                            ->rows(6)
                            ->required()
                            ->placeholder("Tuliskan pesan menarik untuk pelanggan Anda di sini...\nContoh: Nikmati potongan harga spesial hingga 20% untuk semua varian dodol kami!")
                            ->columnSpanFull(),

                        TextInput::make('action_url')
                            ->label('URL Tombol Aksi (CTA Link)')
                            ->placeholder('/promos atau /products')
                            ->helperText('Default akan mengarahkan pelanggan ke halaman /promos.')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
