<?php

namespace App\Filament\Resources\CustomerReviews\Tables;

use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomerReviewsTable
{
    public static function configure(Table $table): Table
    {
        $storeName = (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));

        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->description(fn(ProductReview $record): string => $record->product?->category?->name ?? '-'),

                TextColumn::make('customer.name')
                    ->label('Pembeli')
                    ->searchable()
                    ->sortable()
                    ->description(fn(ProductReview $record): string => $record->customer?->email ?? '-'),

                TextColumn::make('rating')
                    ->label('Rating')
                    ->sortable()
                    ->formatStateUsing(fn(int $state): string => str_repeat('⭐', $state) . " ({$state}/5)"),

                TextColumn::make('review')
                    ->label('Ulasan')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('images_count')
                    ->label('Foto')
                    ->counts('images')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_verified_purchase')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        ProductReview::STATUS_APPROVED => 'success',
                        ProductReview::STATUS_PENDING => 'warning',
                        ProductReview::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                TextColumn::make('reply.reply')
                    ->label('Balasan Toko')
                    ->badge()
                    ->formatStateUsing(fn($state): string => filled($state) ? 'Sudah Dibalas' : 'Belum Dibalas')
                    ->color(fn($state): string => filled($state) ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('rating')
                    ->label('Rating')
                    ->options([
                        5 => '⭐⭐⭐⭐⭐ 5 Bintang',
                        4 => '⭐⭐⭐⭐ 4 Bintang',
                        3 => '⭐⭐⭐ 3 Bintang',
                        2 => '⭐⭐ 2 Bintang',
                        1 => '⭐ 1 Bintang',
                    ]),

                SelectFilter::make('status')
                    ->label('Status Moderasi')
                    ->options([
                        ProductReview::STATUS_APPROVED => 'Approved',
                        ProductReview::STATUS_PENDING => 'Pending',
                        ProductReview::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                // Balas Ulasan Resmi Toko
                Action::make('reply')
                    ->label('Balas Toko')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->modalHeading("Balasan Resmi {$storeName}")
                    ->modalDescription("Balasan ini akan tampil di detail produk sebagai tanggapan resmi dari {$storeName}.")
                    ->form([
                        Textarea::make('reply')
                            ->label('Teks Balasan Resmi')
                            ->required()
                            ->rows(4)
                            ->placeholder("Terima kasih atas ulasan dan kepercayaannya kepada {$storeName}..."),
                    ])
                    ->fillForm(fn(ProductReview $record): array => [
                        'reply' => $record->reply?->reply ?? '',
                    ])
                    ->action(function (ProductReview $record, array $data): void {
                        app(ProductReviewService::class)->saveAdminReply($record, $data['reply'], auth()->user());

                        Notification::make()
                            ->title('Balasan resmi toko berhasil disimpan')
                            ->success()
                            ->send();
                    }),

                // Setujui Ulasan
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(ProductReview $record): bool => $record->status !== ProductReview::STATUS_APPROVED)
                    ->action(function (ProductReview $record): void {
                        app(ProductReviewService::class)->approveReview($record);

                        Notification::make()
                            ->title('Ulasan berhasil disetujui')
                            ->success()
                            ->send();
                    }),

                // Tolak / Sembunyikan Ulasan
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak / Sembunyikan Ulasan')
                    ->modalDescription('Ulasan ini tidak akan ditampilkan pada halaman produk dan tidak akan dihitung dalam rating rata-rata.')
                    ->visible(fn(ProductReview $record): bool => $record->status !== ProductReview::STATUS_REJECTED)
                    ->action(function (ProductReview $record): void {
                        app(ProductReviewService::class)->rejectReview($record);

                        Notification::make()
                            ->title('Ulasan disembunyikan / ditolak')
                            ->danger()
                            ->send();
                    }),

                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
