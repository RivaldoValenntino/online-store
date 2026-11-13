<x-filament-widgets::widget>
    <style>
        .fi-op-widget-card {
            background-color: #111827;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1.25rem;
            color: #f3f4f6;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3);
        }
        .fi-op-widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 0.875rem;
        }
        .fi-op-widget-title-box {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }
        .fi-op-icon-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 0.5rem;
            flex-shrink: 0;
        }
        .fi-op-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 0.875rem;
            border-radius: 0.625rem;
            margin-bottom: 0.625rem;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background-color: rgba(255, 255, 255, 0.02);
            transition: all 0.15s ease-in-out;
        }
        .fi-op-item:hover {
            background-color: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.12);
        }
        .fi-op-item-content {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            min-width: 0;
            flex: 1;
        }
        .fi-op-item-title-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.2rem;
        }
        .fi-op-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.35rem 0.65rem;
            border-radius: 0.375rem;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.15s ease;
        }
        .fi-op-badge-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.1rem 0.45rem;
            font-size: 10px;
            font-weight: 800;
            line-height: 1.2;
        }
        .fi-op-widget-card svg,
        .fi-op-widget-card .fi-icon {
            display: inline-block !important;
            vertical-align: middle;
        }
        .fi-op-icon-svg svg,
        .fi-op-icon-svg .fi-icon {
            width: 16px !important;
            height: 16px !important;
            min-width: 16px !important;
            max-width: 16px !important;
            min-height: 16px !important;
            max-height: 16px !important;
            display: inline-block !important;
        }
        .fi-op-header-svg svg,
        .fi-op-header-svg .fi-icon {
            width: 18px !important;
            height: 18px !important;
            min-width: 18px !important;
            max-width: 18px !important;
            min-height: 18px !important;
            max-height: 18px !important;
            display: inline-block !important;
        }
        .fi-op-btn-svg svg,
        .fi-op-btn-svg .fi-icon {
            width: 12px !important;
            height: 12px !important;
            min-width: 12px !important;
            max-width: 12px !important;
            min-height: 12px !important;
            max-height: 12px !important;
            display: inline-block !important;
        }
    </style>

    <div class="fi-op-widget-card">
        {{-- Header Panel Perhatian --}}
        <div class="fi-op-widget-header">
            <div class="fi-op-widget-title-box">
                <div class="fi-op-icon-badge fi-op-header-svg" style="background-color: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                    <x-filament::icon icon="heroicon-o-bell-alert" />
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 13px; font-weight: 700; color: #ffffff;">Panel Perhatian Operasional</h3>
                    <p style="margin: 0; font-size: 11px; color: #9ca3af;">Tindakan mendesak yang memerlukan intervensi admin</p>
                </div>
            </div>
            @php
                $totalAlerts = $totalLowStockCount + $unpaidCount + $waitingConfirmationCount + $readyToShipCount + $pendingReviewsCount;
            @endphp
            @if ($totalAlerts > 0)
                <span class="fi-op-badge-pill" style="background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);">
                    ● {{ $totalAlerts }} Perhatian
                </span>
            @else
                <span class="fi-op-badge-pill" style="background-color: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);">
                    ✓ Semua Terkendali
                </span>
            @endif
        </div>

        {{-- Alerts List --}}
        <div>
            {{-- 1. Stok Menipis / Habis --}}
            <div class="fi-op-item" style="{{ $totalOutOfStockCount > 0 ? 'border-color: rgba(239, 68, 68, 0.3); background-color: rgba(239, 68, 68, 0.04);' : ($totalLowStockCount > 0 ? 'border-color: rgba(245, 158, 11, 0.3); background-color: rgba(245, 158, 11, 0.04);' : '') }}">
                <div class="fi-op-item-content">
                    <div class="fi-op-icon-svg" style="color: {{ $totalOutOfStockCount > 0 ? '#ef4444' : ($totalLowStockCount > 0 ? '#f59e0b' : '#6b7280') }}; margin-top: 1px;">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                    </div>
                    <div style="min-width: 0;">
                        <div class="fi-op-item-title-row">
                            <span style="font-size: 12px; font-weight: 700; color: #ffffff;">Stok Produk Kritis & Habis</span>
                            <span class="fi-op-badge-pill" style="{{ $totalOutOfStockCount > 0 ? 'background-color: #ef4444; color: #ffffff;' : ($totalLowStockCount > 0 ? 'background-color: #f59e0b; color: #ffffff;' : 'background-color: #374151; color: #9ca3af;') }}">
                                {{ $totalLowStockCount }} Item
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 11px; color: #9ca3af; line-height: 1.3;">
                            @if ($totalOutOfStockCount > 0)
                                <b style="color: #f87171;">{{ $totalOutOfStockCount }} habis total</b>, {{ $totalLowStockCount - $totalOutOfStockCount }} stok menipis (<= 5).
                            @elseif ($totalLowStockCount > 0)
                                {{ $totalLowStockCount }} produk/varian tersisa <= 5 unit.
                            @else
                                Seluruh stok produk mencukupi kebutuhan penjualan.
                            @endif
                        </p>
                    </div>
                </div>

                <a href="{{ $productUrl }}" class="fi-op-btn fi-op-btn-svg" style="{{ $totalLowStockCount > 0 ? 'background-color: #1f2937; color: #e5e7eb; border: 1px solid #374151;' : 'background-color: transparent; color: #4b5563; pointer-events: none;' }}">
                    <span>Kelola Stok</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" />
                </a>
            </div>

            {{-- 2. Pesanan Menunggu Pembayaran --}}
            <div class="fi-op-item" style="{{ $unpaidCount > 0 ? 'border-color: rgba(245, 158, 11, 0.3); background-color: rgba(245, 158, 11, 0.04);' : '' }}">
                <div class="fi-op-item-content">
                    <div class="fi-op-icon-svg" style="color: {{ $unpaidCount > 0 ? '#f59e0b' : '#6b7280' }}; margin-top: 1px;">
                        <x-filament::icon icon="heroicon-o-clock" />
                    </div>
                    <div style="min-width: 0;">
                        <div class="fi-op-item-title-row">
                            <span style="font-size: 12px; font-weight: 700; color: #ffffff;">Pesanan Belum Bayar</span>
                            <span class="fi-op-badge-pill" style="{{ $unpaidCount > 0 ? 'background-color: #f59e0b; color: #ffffff;' : 'background-color: #374151; color: #9ca3af;' }}">
                                {{ $unpaidCount }}
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 11px; color: #9ca3af; line-height: 1.3;">
                            @if ($unpaidCount > 0)
                                Rp {{ number_format($unpaidTotal, 0, ',', '.') }} stok tertahan sementara menunggu pelunasan.
                            @else
                                Tidak ada transaksi yang sedang menunggu pembayaran.
                            @endif
                        </p>
                    </div>
                </div>

                <a href="{{ $unpaidUrl }}" class="fi-op-btn fi-op-btn-svg" style="{{ $unpaidCount > 0 ? 'background-color: #1f2937; color: #e5e7eb; border: 1px solid #374151;' : 'background-color: transparent; color: #4b5563; pointer-events: none;' }}">
                    <span>Lihat Pesanan</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" />
                </a>
            </div>

            {{-- 3. Pesanan Sudah Dibayar Perlu Diproses --}}
            <div class="fi-op-item" style="{{ $waitingConfirmationCount > 0 ? 'border-color: rgba(59, 130, 246, 0.35); background-color: rgba(59, 130, 246, 0.05);' : '' }}">
                <div class="fi-op-item-content">
                    <div class="fi-op-icon-svg" style="color: {{ $waitingConfirmationCount > 0 ? '#3b82f6' : '#6b7280' }}; margin-top: 1px;">
                        <x-filament::icon icon="heroicon-o-check-badge" />
                    </div>
                    <div style="min-width: 0;">
                        <div class="fi-op-item-title-row">
                            <span style="font-size: 12px; font-weight: 700; color: #ffffff;">Lunas & Perlu Diproses</span>
                            <span class="fi-op-badge-pill" style="{{ $waitingConfirmationCount > 0 ? 'background-color: #2563eb; color: #ffffff;' : 'background-color: #374151; color: #9ca3af;' }}">
                                {{ $waitingConfirmationCount }}
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 11px; color: #9ca3af; line-height: 1.3;">
                            @if ($waitingConfirmationCount > 0)
                                Pembayaran terverifikasi dan butuh penyiapan barang sebelum kirim.
                            @else
                                Semua pesanan lunas telah dikonfirmasi.
                            @endif
                        </p>
                    </div>
                </div>

                <a href="{{ $waitingConfirmationUrl }}" class="fi-op-btn fi-op-btn-svg" style="{{ $waitingConfirmationCount > 0 ? 'background-color: #2563eb; color: #ffffff;' : 'background-color: transparent; color: #4b5563; pointer-events: none;' }}">
                    <span>Proses Sekarang</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" />
                </a>
            </div>

            {{-- 4. Pesanan Perlu Dikirim / Resi Biteship --}}
            <div class="fi-op-item" style="{{ $readyToShipCount > 0 ? 'border-color: rgba(99, 102, 241, 0.35); background-color: rgba(99, 102, 241, 0.05);' : '' }}">
                <div class="fi-op-item-content">
                    <div class="fi-op-icon-svg" style="color: {{ $readyToShipCount > 0 ? '#6366f1' : '#6b7280' }}; margin-top: 1px;">
                        <x-filament::icon icon="heroicon-o-truck" />
                    </div>
                    <div style="min-width: 0;">
                        <div class="fi-op-item-title-row">
                            <span style="font-size: 12px; font-weight: 700; color: #ffffff;">Siap Kirim / Pickup Biteship</span>
                            <span class="fi-op-badge-pill" style="{{ $readyToShipCount > 0 ? 'background-color: #4f46e5; color: #ffffff;' : 'background-color: #374151; color: #9ca3af;' }}">
                                {{ $readyToShipCount }}
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 11px; color: #9ca3af; line-height: 1.3;">
                            @if ($readyToShipCount > 0)
                                Paket dalam proses packing / butuh resi kurir penjemput.
                            @else
                                Tidak ada antrean pengiriman tertunda.
                            @endif
                        </p>
                    </div>
                </div>

                <a href="{{ $readyToShipUrl }}" class="fi-op-btn fi-op-btn-svg" style="{{ $readyToShipCount > 0 ? 'background-color: #1f2937; color: #e5e7eb; border: 1px solid #374151;' : 'background-color: transparent; color: #4b5563; pointer-events: none;' }}">
                    <span>Kirim Kurir</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" />
                </a>
            </div>

            {{-- 5. Review yang Belum Dimoderasi --}}
            <div class="fi-op-item" style="{{ $pendingReviewsCount > 0 ? 'border-color: rgba(168, 85, 247, 0.35); background-color: rgba(168, 85, 247, 0.05);' : '' }}">
                <div class="fi-op-item-content">
                    <div class="fi-op-icon-svg" style="color: {{ $pendingReviewsCount > 0 ? '#a855f7' : '#6b7280' }}; margin-top: 1px;">
                        <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" />
                    </div>
                    <div style="min-width: 0;">
                        <div class="fi-op-item-title-row">
                            <span style="font-size: 12px; font-weight: 700; color: #ffffff;">Ulasan Menunggu Moderasi</span>
                            <span class="fi-op-badge-pill" style="{{ $pendingReviewsCount > 0 ? 'background-color: #9333ea; color: #ffffff;' : 'background-color: #374151; color: #9ca3af;' }}">
                                {{ $pendingReviewsCount }}
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 11px; color: #9ca3af; line-height: 1.3;">
                            @if ($pendingReviewsCount > 0)
                                Ulasan pembeli perlu ditinjau sebelum tampil di katalog publik.
                            @else
                                Seluruh ulasan pembeli sudah selesai dimoderasi.
                            @endif
                        </p>
                    </div>
                </div>

                <a href="{{ $pendingReviewsUrl }}" class="fi-op-btn fi-op-btn-svg" style="{{ $pendingReviewsCount > 0 ? 'background-color: #9333ea; color: #ffffff;' : 'background-color: transparent; color: #4b5563; pointer-events: none;' }}">
                    <span>Tinjau Ulasan</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" />
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
