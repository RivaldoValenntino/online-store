<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProductsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 6,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('5 Produk Terlaris')
            ->description('Paling banyak terjual dari transaksi yang sudah dibayar')
            ->query(
                Product::query()
                    ->select('products.id', 'products.name', 'products.category_id')
                    ->selectRaw('COALESCE(SUM(order_items.qty), 0) as total_sold')
                    ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as total_revenue')
                    ->join('order_items', 'products.id', '=', 'order_items.product_id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.payment_status', 'paid')
                    ->groupBy('products.id', 'products.name', 'products.category_id')
                    ->orderByDesc('total_sold')
                    ->orderBy('products.id', 'asc')
                    ->limit(5)
                    ->with(['primaryImage', 'category'])
            )
            ->columns([
                ImageColumn::make('primaryImage.image')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->size(40)
                    ->defaultImageUrl(url('/images/image-not-found.jpg')),

                TextColumn::make('name')
                    ->label('Produk')
                    ->weight('bold')
                    ->description(fn(Product $record) => $record->category?->name ?? '-'),

                TextColumn::make('total_sold')
                    ->label('Terjual')
                    ->badge()
                    ->color('success')
                    ->suffix(' unit')
                    ->alignCenter(),

                TextColumn::make('total_revenue')
                    ->label('Pendapatan')
                    ->money('IDR', locale: 'id')
                    ->weight('bold')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada produk terjual')
            ->emptyStateDescription('Statistik akan muncul setelah pesanan berbayar masuk.');
    }
}
