<?php

namespace App\Filament\Resources\CustomerReviews\Pages;

use App\Filament\Resources\CustomerReviews\CustomerReviewResource;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerReview extends ViewRecord
{
    protected static string $resource = CustomerReviewResource::class;

    protected function getHeaderActions(): array
    {
        $storeName = (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));

        return [
            Action::make('reply')
                ->label('Balas / Edit Balasan Toko')
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

            Action::make('reject')
                ->label('Tolak / Sembunyikan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn(ProductReview $record): bool => $record->status !== ProductReview::STATUS_REJECTED)
                ->action(function (ProductReview $record): void {
                    app(ProductReviewService::class)->rejectReview($record);

                    Notification::make()
                        ->title('Ulasan disembunyikan / ditolak')
                        ->danger()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
