<?php

use App\Models\Order;
use App\Services\BiteshipService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tracking Pesanan')] class extends Component {
    public Order $order;

    public ?array $tracking = null;
    public ?string $trackingError = null;

    public function mount(Order $order): void
    {
        abort_unless($order->customer_id === Auth::guard('customer')->id(), 403);

        $this->order = $order;
        $this->tracking = $this->localTracking();

        if ($this->order->biteship_order_id) {
            $this->refreshTracking();
        }
    }

    public function refreshTracking(): void
    {
        $this->trackingError = null;

        if (!$this->order->biteship_order_id) {
            $this->trackingError = 'Pesanan belum dibuat di Biteship.';
            return;
        }

        try {
            $response = app(BiteshipService::class)->getOrder($this->order->biteship_order_id);
            $this->tracking = $this->normalizeTracking($response);

            $this->order->refresh();
        } catch (\Throwable $e) {
            report($e);

            $this->trackingError = 'Gagal mengambil tracking dari Biteship. Coba lagi beberapa saat.';
        }
    }

    #[Computed]
    public function hasBiteshipOrder(): bool
    {
        return filled($this->order->biteship_order_id);
    }

    public function localTracking(): array
    {
        return [
            'status' => $this->order->shipping_status ?: 'pending',
            'status_label' => $this->statusLabel($this->order->shipping_status ?: 'pending'),
            'courier' => strtoupper((string) ($this->order->shipping_courier ?: '-')),
            'service' => $this->order->shipping_service,
            'waybill' => $this->order->tracking_number ?: $this->order->biteship_waybill_id,
            'label_url' => $this->order->shipping_label_url,
            'events' => $this->localEvents(),
            'raw' => null,
        ];
    }

    public function normalizeTracking(array $response): array
    {
        $status = data_get($response, 'status') ?? (data_get($response, 'data.status') ?? (data_get($response, 'order.status') ?? ($this->order->shipping_status ?? 'pending')));

        $events = collect(data_get($response, 'history') ?? (data_get($response, 'data.history') ?? (data_get($response, 'courier_tracking.history') ?? (data_get($response, 'data.courier_tracking.history') ?? (data_get($response, 'trackings') ?? (data_get($response, 'data.trackings') ?? []))))))
            ->map(fn($event) => $this->normalizeEvent((array) $event))
            ->filter(fn($event) => filled($event['description']) || filled($event['status']))
            ->values()
            ->all();

        return [
            'status' => $status,
            'status_label' => $this->statusLabel((string) $status),
            'courier' => strtoupper((string) (data_get($response, 'courier.company') ?? (data_get($response, 'courier_company') ?? $this->order->shipping_courier) ?: '-')),
            'service' => data_get($response, 'courier.type') ?? (data_get($response, 'courier_type') ?? $this->order->shipping_service),
            'waybill' => data_get($response, 'courier.waybill_id') ?? (data_get($response, 'courier_waybill_id') ?? (data_get($response, 'waybill_id') ?? ($this->order->tracking_number ?? $this->order->biteship_waybill_id))),
            'label_url' => data_get($response, 'courier.link') ?? (data_get($response, 'courier_link') ?? $this->order->shipping_label_url),
            'events' => $events ?: $this->localEvents(),
            'raw' => $response,
        ];
    }

    public function normalizeEvent(array $event): array
    {
        $date = Arr::first([$event['updated_at'] ?? null, $event['created_at'] ?? null, $event['datetime'] ?? null, $event['date'] ?? null, $event['time'] ?? null], fn($value) => filled($value));

        return [
            'date' => $date,
            'status' => $event['status'] ?? ($event['code'] ?? null),
            'description' => $event['description'] ?? ($event['note'] ?? ($event['message'] ?? ($event['status'] ?? null))),
            'location' => $event['location'] ?? ($event['city'] ?? null),
        ];
    }

    public function localEvents(): array
    {
        return collect([
            [
                'date' => $this->order->created_at,
                'status' => 'pending_payment',
                'description' => 'Pesanan dibuat.',
                'location' => null,
            ],
            $this->order->paid_at
                ? [
                    'date' => $this->order->paid_at,
                    'status' => 'paid',
                    'description' => 'Pembayaran diterima.',
                    'location' => null,
                ]
                : null,
            $this->order->shipment_created_at
                ? [
                    'date' => $this->order->shipment_created_at,
                    'status' => 'processed',
                    'description' => 'Pesanan terkonfirmasi dan siap dikirim.',
                    'location' => null,
                ]
                : null,
            $this->order->shipped_at
                ? [
                    'date' => $this->order->shipped_at,
                    'status' => 'shipped',
                    'description' => 'Paket sedang dikirim.',
                    'location' => null,
                ]
                : null,
            $this->order->delivered_at
                ? [
                    'date' => $this->order->delivered_at,
                    'status' => 'delivered',
                    'description' => 'Paket sudah diterima.',
                    'location' => null,
                ]
                : null,
        ])
            ->filter()
            ->values()
            ->all();
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu Diproses',
            'waiting_shipment' => 'Menunggu Pengiriman',
            'processed', 'confirmed', 'allocated', 'scheduled', 'picking_up', 'courier_allocated', 'on_process', 'on_progress', 'dropping_off' => 'Diproses Ekspedisi',
            'shipped', 'pickup', 'picked', 'picked_up', 'in_transit' => 'Dalam Pengiriman',
            'delivered', 'done', 'completed' => 'Terkirim',
            'cancelled', 'canceled' => 'Dibatalkan',
            'failed' => 'Gagal Dikirim',
            'returned' => 'Dikembalikan',
            default => ucfirst(str_replace('_', ' ', (string) ($status ?: '-'))),
        };
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'delivered', 'done', 'completed' => 'badge-success',
            'shipped', 'pickup', 'picked', 'picked_up', 'in_transit' => 'badge-primary',
            'processed', 'confirmed', 'allocated', 'scheduled', 'picking_up', 'courier_allocated', 'on_process', 'on_progress', 'dropping_off' => 'badge-info',
            'cancelled', 'canceled', 'failed', 'returned' => 'badge-error',
            default => 'badge-warning',
        };
    }

    /**
     * Icon per tahapan status, mirip timeline Biteship:
     * - map-pin  -> order diterima / dikonfirmasi
     * - truck    -> proses penjemputan / dalam perjalanan
     * - check    -> selesai / terkirim
     * - x        -> batal / gagal / retur
     */
    public function statusIcon(?string $status): string
    {
        return match ($status) {
            'pending', 'pending_payment', 'paid', 'confirmed' => 'heroicon-o-map-pin',
            'processed', 'allocated', 'scheduled', 'courier_allocated', 'on_process', 'on_progress' => 'heroicon-o-clipboard-document-check',
            'picking_up', 'picked', 'picked_up', 'pickup', 'shipped', 'dropping_off', 'in_transit' => 'heroicon-o-truck',
            'delivered', 'done', 'completed' => 'heroicon-o-check-circle',
            'cancelled', 'canceled', 'failed', 'returned' => 'heroicon-o-x-circle',
            default => 'heroicon-o-map-pin',
        };
    }

    /**
     * Warna lingkaran icon: status akhir (delivered/cancelled/failed) selalu
     * dapat warna solid meski bukan event teratas, sisanya ikut aturan
     * "terbaru = primary, lainnya = abu-abu" seperti sebelumnya.
     */
    public function statusIconBg(?string $status, bool $isLatest): string
    {
        if (in_array($status, ['delivered', 'done', 'completed'], true)) {
            return 'bg-success text-success-content';
        }

        if (in_array($status, ['cancelled', 'canceled', 'failed', 'returned'], true)) {
            return 'bg-error text-error-content';
        }

        return $isLatest ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/50';
    }

    public function formattedDateTime($date): string
    {
        if (!$date) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y H:i');
        } catch (\Throwable) {
            return (string) $date;
        }
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <div wire:loading.flex wire:target="refreshTracking"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <p class="text-sm font-semibold text-base-content/70">Mengambil tracking...</p>
        </div>
    </div>

    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.orders.show', $order) }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-black">Tracking Paket</h1>
                <p class="truncate text-xs text-base-content/50">{{ $order->order_number }}</p>
            </div>

            <button type="button" wire:click="refreshTracking" wire:loading.attr="disabled"
                wire:target="refreshTracking" class="btn btn-circle btn-sm btn-ghost" @disabled(!$this->hasBiteshipOrder)>
                <x-heroicon-o-arrow-path class="h-5 w-5" />
            </button>
        </div>
    </header>

    <main class="space-y-4 p-4">
        @if ($trackingError)
            <div class="alert alert-error alert-soft rounded-2xl">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                <span class="text-sm">{{ $trackingError }}</span>
            </div>
        @endif

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-base-content/50">Status Paket</p>
                    <h2 class="mt-1 text-lg sm:text-xl font-black">
                        {{ $tracking['status_label'] ?? '-' }}
                    </h2>
                </div>

                <span
                    class="badge {{ $this->statusBadgeClass($tracking['status'] ?? null) }} shrink-0 whitespace-nowrap text-xs font-bold py-2 px-2.5 h-auto leading-normal">
                    {{ strtoupper(str_replace('_', ' ', $tracking['status'] ?? '-')) }}
                </span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl bg-base-200 p-3">
                    <p class="text-xs text-base-content/50">Kurir</p>
                    <p class="font-bold">{{ $tracking['courier'] ?? '-' }} {{ $tracking['service'] ?? '' }}</p>
                </div>

                <div class="rounded-2xl bg-base-200 p-3">
                    <p class="text-xs text-base-content/50">Resi</p>
                    <p class="break-all font-bold">{{ $tracking['waybill'] ?: '-' }}</p>
                </div>
            </div>

            @if (!$this->hasBiteshipOrder)
                <div class="mt-4 rounded-2xl bg-warning/10 p-3 text-sm text-warning">
                    <div class="flex gap-2">
                        <x-heroicon-o-clock class="h-5 w-5 shrink-0" />
                        <p class="font-medium">Pesanan sedang diproses untuk dikirim.</p>
                    </div>
                </div>
            @endif

            @if ($tracking['label_url'] ?? null)
                <a href="{{ $tracking['label_url'] }}" target="_blank"
                    class="btn btn-outline btn-primary mt-4 w-full rounded-full">
                    <x-heroicon-o-document-text class="h-5 w-5" />
                    Buka Label / Tracking
                </a>
            @endif
        </section>

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-black">Riwayat Pengiriman</h2>
                    <p class="text-xs text-base-content/50">Update dari status lokal dan Biteship</p>
                </div>
            </div>

            <div class="space-y-4">
                @forelse (($tracking['events'] ?? []) as $index => $event)
                    <div class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $this->statusIconBg($event['status'] ?? null, $index === 0) }}">
                                <x-dynamic-component :component="$this->statusIcon($event['status'] ?? null)" class="h-5 w-5" />
                            </div>

                            @if (!$loop->last)
                                <div class="mt-2 h-full min-h-8 w-px bg-base-300"></div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 pb-1">
                            <p class="font-bold">
                                {{ $event['description'] ?: $this->statusLabel($event['status'] ?? null) }}</p>

                            @if ($event['location'])
                                <p class="mt-1 text-sm text-base-content/60">{{ $event['location'] }}</p>
                            @endif

                            <p class="mt-1 text-xs text-base-content/50">
                                {{ $this->formattedDateTime($event['date'] ?? null) }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl bg-base-200 p-4 text-center">
                        <p class="text-sm font-bold">Belum ada riwayat tracking</p>
                        <p class="mt-1 text-xs text-base-content/50">
                            Riwayat akan muncul setelah paket diproses kurir.
                        </p>
                    </div>
                @endforelse
            </div>
        </section>
    </main>
</div>
