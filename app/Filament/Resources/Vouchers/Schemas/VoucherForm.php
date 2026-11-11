<?php

namespace App\Filament\Resources\Vouchers\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\VoucherService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Utama Voucher')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Voucher')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->suffixAction(
                                \Filament\Actions\Action::make('generateCode')
                                    ->icon('heroicon-m-sparkles')
                                    ->tooltip('Generate Kode Otomatis')
                                    ->action(function ($set) {
                                        $set('code', app(VoucherService::class)->generateUniqueCode());
                                    })
                            ),

                        TextInput::make('name')
                            ->label('Nama Promo / Voucher')
                            ->required()
                            ->placeholder('Contoh: Diskon Gajian 20%')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Deskripsi & Ketentuan')
                            ->placeholder('Syarat dan ketentuan penggunaan voucher ini...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Nilai & Perhitungan Diskon')
                    ->schema([
                        Select::make('type')
                            ->label('Jenis Diskon')
                            ->options(Voucher::types())
                            ->required()
                            ->reactive(),

                        TextInput::make('amount')
                            ->label('Nilai Diskon')
                            ->numeric()
                            ->required()
                            ->helperText('Persentase (misal: 10 untuk 10%) atau Nominal Rupiah.'),

                        TextInput::make('min_spend')
                            ->label('Minimal Belanja (Rp)')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->helperText('0 jika tanpa minimal belanja.'),

                        TextInput::make('max_discount')
                            ->label('Maksimal Potongan (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->nullable()
                            ->helperText('Batas maksimal diskon (wajib diperhatikan jika jenis persentase).'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Kuota & Pembatasan')
                    ->schema([
                        TextInput::make('total_quota')
                            ->label('Kuota Keseluruhan')
                            ->numeric()
                            ->nullable()
                            ->helperText('Kosongkan jika kuota tidak terbatas (unlimited).'),

                        TextInput::make('user_limit')
                            ->label('Batas Penggunaan Per Pengguna')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->helperText('Berapa kali 1 akun pelanggan dapat menggunakan voucher ini.'),

                        Select::make('target_audience')
                            ->label('Target Pengguna')
                            ->options([
                                'all' => 'Semua Pengguna Terdaftar',
                                'new_users' => 'Khusus Pengguna Baru (Belum pernah order)',
                            ])
                            ->default('all')
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Aktifkan Voucher')
                            ->default(true)
                            ->required(),

                        DateTimePicker::make('starts_at')
                            ->label('Tanggal Mulai')
                            ->nullable(),

                        DateTimePicker::make('expires_at')
                            ->label('Tanggal Berakhir')
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Ketentuan Produk & Kategori (Opsional)')
                    ->schema([
                        Select::make('eligible_category_ids')
                            ->label('Berlaku Untuk Kategori')
                            ->multiple()
                            ->options(fn () => Category::pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Kosongkan jika berlaku untuk semua kategori.'),

                        Select::make('eligible_product_ids')
                            ->label('Berlaku Untuk Produk Tertentu')
                            ->multiple()
                            ->options(fn () => Product::pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Kosongkan jika berlaku untuk semua produk.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }
}
