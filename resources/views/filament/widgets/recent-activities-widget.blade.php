<x-filament-widgets::widget>
    <style>
        .fi-act-widget-card {
            background-color: #111827;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1.25rem;
            color: #f3f4f6;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3);
        }
        .fi-act-widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 0.875rem;
        }
        .fi-act-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .fi-act-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .fi-act-icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 0.5rem;
            flex-shrink: 0;
        }
        .fi-act-widget-card svg,
        .fi-act-widget-card .fi-icon {
            display: inline-block !important;
            vertical-align: middle;
        }
        .fi-act-icon-box svg,
        .fi-act-icon-box .fi-icon {
            width: 16px !important;
            height: 16px !important;
            min-width: 16px !important;
            max-width: 16px !important;
            min-height: 16px !important;
            max-height: 16px !important;
            display: inline-block !important;
        }
        .fi-act-header-svg svg,
        .fi-act-header-svg .fi-icon {
            width: 18px !important;
            height: 18px !important;
            min-width: 18px !important;
            max-width: 18px !important;
            min-height: 18px !important;
            max-height: 18px !important;
            display: inline-block !important;
        }
    </style>

    <div class="fi-act-widget-card">
        {{-- Header --}}
        <div class="fi-act-widget-header">
            <div style="display: flex; align-items: center; gap: 0.625rem;">
                <div class="fi-act-icon-box fi-act-header-svg" style="background-color: rgba(99, 102, 241, 0.15); color: #818cf8;">
                    <x-filament::icon icon="heroicon-o-bolt" />
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 13px; font-weight: 700; color: #ffffff;">Aktivitas Operasional Terbaru</h3>
                    <p style="margin: 0; font-size: 11px; color: #9ca3af;">Log transaksi, pembayaran, ulasan & pergerakan katalog</p>
                </div>
            </div>
            <span style="font-size: 10px; font-weight: 700; color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 2px 8px; border-radius: 9999px;">
                ● Live Real-time
            </span>
        </div>

        {{-- Timeline List --}}
        <div>
            @forelse ($activities as $act)
                @php
                    $color = $act['color'] ?? 'gray';
                    $colorStyles = match($color) {
                        'blue' => 'background-color: rgba(59, 130, 246, 0.15); color: #60a5fa;',
                        'emerald' => 'background-color: rgba(16, 185, 129, 0.15); color: #34d399;',
                        'indigo' => 'background-color: rgba(99, 102, 241, 0.15); color: #818cf8;',
                        'amber' => 'background-color: rgba(245, 158, 11, 0.15); color: #fbbf24;',
                        'purple' => 'background-color: rgba(168, 85, 247, 0.15); color: #c084fc;',
                        default => 'background-color: rgba(107, 114, 128, 0.15); color: #9ca3af;',
                    };
                    $time = $act['time'] instanceof \Carbon\Carbon ? $act['time'] : \Carbon\Carbon::parse($act['time']);
                @endphp
                <div class="fi-act-item">
                    {{-- Icon Pill --}}
                    <div class="fi-act-icon-box" style="{{ $colorStyles }}">
                        <x-filament::icon :icon="$act['icon']" />
                    </div>

                    {{-- Activity Content --}}
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                            <a href="{{ $act['url'] ?? '#' }}" style="font-size: 12px; font-weight: 700; color: #f9fafb; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ $act['title'] }}
                            </a>
                            <span style="font-size: 10px; color: #9ca3af; flex-shrink: 0;">
                                {{ $time->diffForHumans() }}
                            </span>
                        </div>
                        <p style="margin: 2px 0 0 0; font-size: 11px; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $act['description'] }}
                        </p>
                    </div>
                </div>
            @empty
                <div style="padding: 2rem 1rem; text-align: center;">
                    <p style="margin: 0; font-size: 12px; font-weight: 600; color: #9ca3af;">Belum ada aktivitas terbaru</p>
                    <p style="margin: 4px 0 0 0; font-size: 11px; color: #6b7280;">Seluruh riwayat pesanan, pembayaran, dan ulasan akan tampil di sini.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
