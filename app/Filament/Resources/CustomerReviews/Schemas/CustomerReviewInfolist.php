<?php

namespace App\Filament\Resources\CustomerReviews\Schemas;

use App\Models\ProductReview;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pembeli & Transaksi')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextEntry::make('product.name')
                                ->label('Produk')
                                ->badge()
                                ->color('primary'),

                            TextEntry::make('customer.name')
                                ->label('Nama Pembeli'),

                            TextEntry::make('customer.email')
                                ->label('Email Pembeli'),

                            TextEntry::make('order.order_number')
                                ->label('Nomor Pesanan')
                                ->badge(),

                            IconEntry::make('is_verified_purchase')
                                ->label('Verified Purchase')
                                ->boolean(),

                            TextEntry::make('created_at')
                                ->label('Tanggal Ulasan')
                                ->dateTime('d F Y H:i'),
                        ]),
                    ]),

                Section::make('Konten Ulasan & Rating')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextEntry::make('rating')
                                ->label('Rating')
                                ->formatStateUsing(fn(int $state): string => str_repeat('⭐', $state) . " ({$state}/5)"),

                            TextEntry::make('status')
                                ->label('Status Moderasi')
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    ProductReview::STATUS_APPROVED => 'success',
                                    ProductReview::STATUS_PENDING => 'warning',
                                    ProductReview::STATUS_REJECTED => 'danger',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn(string $state): string => ucfirst($state)),
                        ]),

                        TextEntry::make('review')
                            ->label('Teks Ulasan')
                            ->prose(),
                    ]),

                Section::make('Foto Lampiran Ulasan')
                    ->schema([
                        RepeatableEntry::make('images')
                            ->label('Daftar Foto')
                            ->schema([
                                ImageEntry::make('image_path')
                                    ->disk('public')
                                    ->height(120),
                            ])
                            ->grid(3),
                    ])
                    ->visible(fn(ProductReview $record): bool => $record->images()->exists()),

                Section::make('Balasan Resmi Toko')
                    ->schema([
                        TextEntry::make('reply.reply')
                            ->label('Pesan Balasan Toko')
                            ->placeholder('Belum ada balasan resmi.')
                            ->prose(),

                        TextEntry::make('reply.created_at')
                            ->label('Waktu Dibalas')
                            ->dateTime('d F Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
