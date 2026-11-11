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
                            ->options([
                                PromoCampaign::AUDIENCE_ALL => 'Semua Pengguna Terdaftar',
                                PromoCampaign::AUDIENCE_PURCHASED => 'Pengguna yang Pernah Membeli (Repeat Buyer)',
                                PromoCampaign::AUDIENCE_NON_PURCHASED => 'Pengguna yang Belum Pernah Membeli',
                                PromoCampaign::AUDIENCE_NEW => 'Pengguna Baru',
                            ])
                            ->default(PromoCampaign::AUDIENCE_ALL)
                            ->required(),

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
