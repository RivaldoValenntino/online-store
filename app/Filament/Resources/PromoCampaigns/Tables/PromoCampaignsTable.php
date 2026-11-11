<?php

namespace App\Filament\Resources\PromoCampaigns\Tables;

use App\Models\PromoCampaign;
use App\Services\PromoCampaignService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PromoCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Nama Campaign')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('voucher.code')
                    ->label('Voucher')
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),

                TextColumn::make('channels')
                    ->label('Kanal Distribusi')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'banner' => 'Banner',
                        'email' => 'Email Brevo',
                        'in_app' => 'In-App',
                        default => $state,
                    }),

                TextColumn::make('target_audience')
                    ->label('Target')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'all' => 'Semua User',
                        'purchased' => 'Pernah Beli',
                        'non_purchased' => 'Belum Beli',
                        'new_users' => 'User Baru',
                        default => $state,
                    })
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PromoCampaign::STATUS_DRAFT => 'gray',
                        PromoCampaign::STATUS_SCHEDULED => 'warning',
                        PromoCampaign::STATUS_SENDING => 'info',
                        PromoCampaign::STATUS_COMPLETED => 'success',
                        PromoCampaign::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('sent_count')
                    ->label('Terkirim')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        PromoCampaign::STATUS_DRAFT => 'Draft',
                        PromoCampaign::STATUS_SCHEDULED => 'Terjadwal',
                        PromoCampaign::STATUS_SENDING => 'Sedang Dikirim',
                        PromoCampaign::STATUS_COMPLETED => 'Selesai',
                        PromoCampaign::STATUS_FAILED => 'Gagal',
                    ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview Email')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Preview Email Campaign')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (PromoCampaign $record) => new HtmlString(
                        '<div style="max-height: 500px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">' .
                        app(PromoCampaignService::class)->renderEmailPreview($record) .
                        '</div>'
                    )),

                Action::make('blast')
                    ->label('Kirim Blast')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Kirim Blast Promosi')
                    ->modalDescription('Apakah Anda yakin ingin memproses blast promosi ini sekarang? Notifikasi dan email akan dikirim secara asynchronous ke semua pelanggan target.')
                    ->visible(fn (PromoCampaign $record) => $record->status !== PromoCampaign::STATUS_COMPLETED && $record->status !== PromoCampaign::STATUS_SENDING)
                    ->action(function (PromoCampaign $record) {
                        app(PromoCampaignService::class)->dispatchNow($record);

                        Notification::make()
                            ->title('Pengiriman Campaign Dimulai')
                            ->body('Job antrean berhasil dibuat. Email dan notifikasi sedang diproses di background.')
                            ->success()
                            ->send();
                    }),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
