<?php

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\MidtransService;
new #[Title('Detail Pesanan')] class extends Component {
    public Order $order;

    public bool $showCancelModal = false;
    public function mount(Order $order): void
    {
        abort_unless($order->customer_id === Auth::guard('customer')->id(), 403);

        $this->order = $order->load(['items']);
        $this->expireIfNeeded();
    }

    // public function payNow(): void
    // {
    //     $this->order->refresh();

    //     if ($this->order->payment_status === 'paid') {
    //         return;
    //     }

    //     if ($this->order->status !== 'pending_payment') {
    //         $this->addError('payment', 'Pesanan ini sudah tidak bisa dibayar.');
    //         return;
    //     }

    //     if ($this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at)) {
    //         $this->addError('payment', 'Waktu pembayaran pesanan ini sudah kedaluwarsa.');
    //         return;
    //     }

    //     if (!$this->order->midtrans_redirect_url) {
    //         $this->addError('payment', 'Link pembayaran belum tersedia.');
    //         return;
    //     }

    //     $this->redirect($this->order->midtrans_redirect_url);
    // }
    public function payNow(): void
    {
        $this->order->refresh();

        if ($this->order->payment_status === 'paid') {
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibayar.');
            return;
        }

        if ($this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at)) {
            $this->expireIfNeeded();
            $this->addError('payment', 'Waktu pembayaran pesanan ini sudah kedaluwarsa.');
            return;
        }

        if ($this->order->midtrans_redirect_url) {
            $this->redirect($this->order->midtrans_redirect_url);
            return;
        }

        $midtransOrderId = $this->order->order_number . '-PAY-1';

        $snap = app(MidtransService::class)->createSnapTransaction($this->order, $midtransOrderId);

        $this->order->update([
            'midtrans_order_id' => $midtransOrderId,
            'midtrans_snap_token' => $snap->token,
            'midtrans_redirect_url' => $snap->redirect_url,
        ]);

        $this->redirect($snap->redirect_url);
    }
    public function changePaymentMethod(): void
    {
        $this->order->refresh();

        if ($this->order->payment_status === 'paid') {
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibayar.');
            return;
        }

        if ($this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at)) {
            $this->expireIfNeeded();
            $this->addError('payment', 'Waktu pembayaran pesanan ini sudah kedaluwarsa.');
            return;
        }

        $midtransOrderId = $this->order->order_number . '-PAY-' . now()->format('YmdHis');

        $snap = app(MidtransService::class)->createSnapTransaction($this->order, $midtransOrderId);

        $this->order->update([
            'midtrans_order_id' => $midtransOrderId,
            'midtrans_snap_token' => $snap->token,
            'midtrans_redirect_url' => $snap->redirect_url,
        ]);

        $this->redirect($snap->redirect_url);
    }
    public function isPaymentExpired(): bool
    {
        return $this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at) && $this->order->payment_status !== 'paid';
        // return $this->order->status === 'expired' || $this->order->payment_status === 'expired';
    }

    public function formattedDateTime($date): string
    {
        if (!$date) {
            return '-';
        }

        return $date->locale('id')->translatedFormat('l, d F Y H:i');
    }
    public function cancelOrder(): void
    {
        $this->showCancelModal = false;

        $this->order->refresh()->load(['items']);

        if ($this->order->payment_status === 'paid') {
            $this->addError('payment', 'Pesanan yang sudah dibayar tidak bisa dibatalkan.');
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibatalkan.');
            return;
        }

        DB::transaction(function () {
            $order = Order::query()->whereKey($this->order->id)->lockForUpdate()->firstOrFail();

            if ($order->stock_restored_at) {
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                    'shipping_status' => 'cancelled',
                ]);

                return;
            }

            foreach ($order->items()->get() as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->increment('stock', $item->qty);

                    continue;
                }

                if ($item->product_id) {
                    Product::query()->whereKey($item->product_id)->lockForUpdate()->increment('stock', $item->qty);
                }
            }

            if ($order->voucher_id) {
                VoucherUsage::where('order_id', $order->id)->delete();
                $voucher = Voucher::whereKey($order->voucher_id)->lockForUpdate()->first();
                if ($voucher && $voucher->total_used > 0) {
                    $voucher->decrement('total_used');
                }
            }

            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'shipping_status' => 'cancelled',
                'stock_restored_at' => now(),
            ]);
        });

        session()->flash('flash.type', 'success');
        session()->flash('flash.title', 'Pesanan dibatalkan');
        session()->flash('flash.message', 'Pesanan berhasil dibatalkan dan stok produk dikembalikan.');

        $this->redirectRoute('store.orders.show', $this->order->id, navigate: true);
    }
    public function openCancelModal(): void
    {
        $this->resetErrorBag('payment');

        if ($this->order->payment_status === 'paid') {
            $this->addError('payment', 'Pesanan yang sudah dibayar tidak bisa dibatalkan.');
            return;
        }

        if (!in_array($this->order->status, ['pending_payment'], true)) {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibatalkan.');
            return;
        }

        $this->showCancelModal = true;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
    }
    public function expireIfNeeded(): void
    {
        $this->order->refresh();

        if ($this->order->payment_status === 'paid') {
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            return;
        }

        if (!$this->order->payment_expires_at || now()->lessThan($this->order->payment_expires_at)) {
            return;
        }

        app(\App\Jobs\ExpireOrderJob::class, [
            'orderId' => $this->order->id,
        ])->handle();

        $this->order->refresh()->load(['items']);
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.orders') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div>
                <h1 class="text-lg font-black">Detail Pesanan</h1>
                <p class="text-xs text-base-content/50">
                    {{ $order->order_number }}
                </p>
            </div>
        </div>
    </header>

    <main class="space-y-4 p-4">
        @error('payment')
            <div class="alert alert-error alert-soft rounded-2xl">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                <span>{{ $message }}</span>
            </div>
        @enderror

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-base-content/50">Nomor Order</p>
                    <h2 class="font-black">{{ $order->order_number }}</h2>
                </div>

                <div class="badge badge-primary">
                    {{ strtoupper(str_replace('_', ' ', $order->status)) }}
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl bg-base-200 p-3">
                    <p class="text-xs text-base-content/50">Pembayaran</p>
                    <p class="font-bold">{{ strtoupper(str_replace('_', ' ', $order->payment_status)) }}</p>
                </div>

                <div class="rounded-2xl bg-base-200 p-3">
                    <p class="text-xs text-base-content/50">Pengiriman</p>
                    <p class="font-bold">{{ strtoupper(str_replace('_', ' ', $order->shipping_status)) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-black">Info Transaksi</h2>
                    <p class="text-xs text-base-content/50">
                        Detail waktu dan pembayaran
                    </p>
                </div>

                @if ($order->payment_status === 'paid')
                    <span class="badge badge-success">Lunas</span>
                @elseif ($this->isPaymentExpired())
                    <span class="badge badge-error">Kedaluwarsa</span>
                @else
                    <span class="badge badge-warning">Menunggu Bayar</span>
                @endif
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <span class="text-base-content/60">Dibuat</span>
                    <span class="text-right font-bold">
                        {{ $this->formattedDateTime($order->created_at) }}
                    </span>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <span class="text-base-content/60">Batas Bayar</span>
                    <span class="text-right font-bold">
                        {{ $this->formattedDateTime($order->payment_expires_at) }}
                    </span>
                </div>

                @if ($order->paid_at)
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-base-content/60">Dibayar</span>
                        <span class="text-right font-bold text-success">
                            {{ $this->formattedDateTime($order->paid_at) }}
                        </span>
                    </div>
                @endif
            </div>

            @if ($order->status === 'expired' || $order->payment_status === 'expired')
                <div class="mt-4 rounded-2xl border border-error/30 bg-error/10 p-4 text-center">
                    <x-heroicon-o-clock class="mx-auto h-9 w-9 text-error" />

                    <p class="mt-2 font-black text-error">
                        Waktu pembayaran sudah habis
                    </p>

                    <p class="mt-1 text-xs text-base-content/60">
                        Pesanan ini sudah kedaluwarsa dan tidak bisa dibayar lagi.
                    </p>
                </div>
            @elseif ($order->payment_status !== 'paid' && $order->status === 'pending_payment' && $order->payment_expires_at)
                <div x-data="{
                    end: new Date('{{ $order->payment_expires_at->toIso8601String() }}').getTime(),
                    now: Date.now(),
                    timer: null,
                    get remaining() {
                        return Math.max(0, this.end - this.now);
                    },
                    get expired() {
                        return this.remaining <= 0;
                    },
                    get hours() {
                        return Math.floor(this.remaining / 1000 / 60 / 60);
                    },
                    get minutes() {
                        return Math.floor((this.remaining / 1000 / 60) % 60);
                    },
                    get seconds() {
                        return Math.floor((this.remaining / 1000) % 60);
                    },
                    pad(value) {
                        return String(value).padStart(2, '0');
                    },
                    init() {
                        this.timer = setInterval(() => {
                            this.now = Date.now();
                
                            if (this.expired) {
                                clearInterval(this.timer);
                            }
                        }, 1000);
                    }
                }" class="mt-4 rounded-2xl border border-warning/30 bg-warning/10 p-4">
                    <template x-if="!expired">
                        <div>
                            <div class="flex items-center justify-center gap-2 text-warning">
                                <x-heroicon-o-clock class="h-5 w-5" />
                                <p class="text-sm font-black">
                                    Sisa Waktu Pembayaran
                                </p>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                                    <p class="text-2xl font-black text-warning" x-text="pad(hours)"></p>
                                    <p class="mt-1 text-xs text-base-content/50">Jam</p>
                                </div>

                                <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                                    <p class="text-2xl font-black text-warning" x-text="pad(minutes)"></p>
                                    <p class="mt-1 text-xs text-base-content/50">Menit</p>
                                </div>

                                <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                                    <p class="text-2xl font-black text-warning" x-text="pad(seconds)"></p>
                                    <p class="mt-1 text-xs text-base-content/50">Detik</p>
                                </div>
                            </div>

                            {{-- <p class="mt-3 text-center text-xs text-base-content/60">
                                Stok pesanan ini ditahan sementara sampai batas waktu pembayaran.
                            </p> --}}
                        </div>
                    </template>

                    <template x-if="expired">
                        <div class="text-center">
                            <div
                                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-error/10 text-error">
                                <x-heroicon-o-clock class="h-8 w-8" />
                            </div>

                            <p class="mt-3 font-black text-error">
                                Waktu pembayaran sudah habis
                            </p>

                            <p class="mt-1 text-xs text-base-content/60">
                                Pesanan ini sudah melewati batas waktu pembayaran.
                            </p>
                        </div>
                    </template>
                </div>
            @endif
        </section>
        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <h2 class="mb-3 font-black">Alamat Pengiriman</h2>

            <p class="font-bold">{{ $order->recipient_name }}</p>
            <p class="text-sm text-base-content/60">{{ $order->recipient_phone }}</p>
            <p class="mt-2 text-sm leading-relaxed text-base-content/70">
                {{ $order->shipping_address }}
            </p>

            <div class="mt-3 rounded-2xl bg-base-200 p-3 text-sm">
                <p class="font-bold">
                    {{ strtoupper($order->shipping_courier ?? '-') }} {{ $order->shipping_service }}
                </p>
                <p class="text-xs text-base-content/50">
                    Estimasi {{ $order->shipping_etd ?: '-' }}
                </p>
            </div>

            @if ($order->payment_status === 'paid')
                <a href="{{ route('store.orders.tracking', $order) }}" wire:navigate
                    class="btn btn-outline btn-primary mt-3 w-full rounded-full">
                    <x-heroicon-o-truck class="h-5 w-5" />
                    Tracking Paket
                </a>
            @endif
        </section>

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <h2 class="mb-3 font-black">Produk</h2>

            <div class="space-y-3">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-3 border-b border-base-200 pb-3 last:border-b-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="line-clamp-2 text-sm font-bold">
                                {{ $item->product_name }}
                            </p>

                            @if ($item->variant_name)
                                <p class="text-xs text-base-content/50">
                                    {{ $item->variant_name }}
                                </p>
                            @endif

                            <p class="mt-1 text-xs text-base-content/50">
                                x{{ $item->qty }}
                            </p>
                        </div>

                        <p class="shrink-0 text-sm font-black">
                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <h2 class="mb-3 font-black">Ringkasan</h2>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-base-content/60">Subtotal</span>
                    <span class="font-bold">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-base-content/60">Ongkir</span>
                    <span class="font-bold">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                </div>

                @if ($order->discount > 0)
                    <div class="flex justify-between text-success">
                        <span class="flex items-center gap-1">
                            Diskon
                            @if ($order->voucher_code)
                                <span class="badge badge-xs badge-primary font-mono font-bold">{{ $order->voucher_code }}</span>
                            @endif
                        </span>
                        <span class="font-bold">- Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                    </div>
                @endif

                <div class="border-t border-base-200 pt-2">
                    <div class="flex justify-between">
                        <span class="font-black">Total</span>
                        <span class="text-lg font-black text-primary">
                            Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
            @if ($order->payment_status !== 'paid' && $order->status === 'pending_payment' && !$this->isPaymentExpired())
                <div class="mt-4 space-y-3 border-t border-base-200 pt-4">
                    <button type="button" wire:click="payNow" wire:loading.attr="disabled" wire:target="payNow"
                        class="btn btn-primary w-full rounded-full">
                        <span wire:loading.remove wire:target="payNow">
                            Bayar Sekarang
                        </span>

                        <span wire:loading wire:target="payNow" class="flex items-center gap-2">
                            <span class="loading loading-spinner loading-sm"></span>
                            Membuka pembayaran...
                        </span>
                    </button>
                    @if ($order->midtrans_redirect_url)
                        <button type="button" wire:click="changePaymentMethod" wire:loading.attr="disabled"
                            wire:target="changePaymentMethod" class="btn btn-outline btn-primary w-full rounded-full">
                            <span wire:loading.remove wire:target="changePaymentMethod">
                                Ganti Metode Pembayaran
                            </span>

                            <span wire:loading wire:target="changePaymentMethod" class="flex items-center gap-2">
                                <span class="loading loading-spinner loading-sm"></span>
                                Membuka metode baru...
                            </span>
                        </button>
                    @endif
                    <button type="button" wire:click="openCancelModal" wire:loading.attr="disabled"
                        wire:target="openCancelModal" class="btn btn-outline btn-error w-full rounded-full">
                        Batalkan Pesanan
                    </button>


                </div>
            @endif

            {{-- <p class="text-center text-xs text-base-content/50">
                Stok pesanan ini ditahan sementara sampai batas waktu pembayaran.
            </p> --}}
            @if ($order->status === 'cancelled')
                <div class="mt-4 rounded-2xl bg-error/10 p-3 text-sm text-error">
                    <div class="flex gap-2">
                        <x-heroicon-o-x-circle class="h-5 w-5 shrink-0" />
                        <p class="font-medium">Pesanan ini sudah dibatalkan.</p>
                    </div>
                </div>
            @endif

            @if ($order->payment_status === 'paid')
                <div class="mt-4 rounded-2xl bg-success/10 p-3 text-sm text-success">
                    <div class="flex gap-2">
                        <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
                        <p class="font-medium">Pembayaran berhasil diterima.</p>
                    </div>
                </div>
            @endif
            @if ($this->isPaymentExpired() && $order->status === 'pending_payment')
                <div class="mt-4 rounded-2xl bg-error/10 p-3 text-sm text-error">
                    <div class="flex gap-2">
                        <x-heroicon-o-clock class="h-5 w-5 shrink-0" />
                        <div>
                            <p class="font-bold">Waktu pembayaran sudah habis.</p>
                            {{-- <p class="mt-1 text-xs text-error/80">
                                Pesanan ini perlu dibatalkan otomatis agar stok dikembalikan.
                            </p> --}}
                        </div>
                    </div>
                </div>
            @endif
        </section>
    </main>

    @if ($showCancelModal)
        <div class="modal modal-open">
            <div class="modal-box rounded-[2rem]">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-error/10 text-error">
                    <x-heroicon-o-exclamation-triangle class="h-9 w-9" />
                </div>

                <h3 class="mt-4 text-center text-xl font-black">
                    Batalkan Pesanan?
                </h3>

                <p class="mt-2 text-center text-sm leading-relaxed text-base-content/60">
                    Pesanan ini akan dibatalkan dan stok produk akan dikembalikan.
                    Kamu tidak bisa membayar pesanan ini setelah dibatalkan.
                </p>

                <div class="mt-5 rounded-2xl bg-base-200 p-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-base-content/60">Nomor Order</span>
                        <span class="font-bold text-right">{{ $order->order_number }}</span>
                    </div>

                    <div class="mt-2 flex justify-between gap-3">
                        <span class="text-base-content/60">Total</span>
                        <span class="font-black text-primary">
                            Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="modal-action grid grid-cols-2 gap-2">
                    <button type="button" wire:click="closeCancelModal" class="btn btn-ghost rounded-full">
                        Tidak Jadi
                    </button>

                    <button type="button" wire:click="cancelOrder" wire:loading.attr="disabled"
                        wire:target="cancelOrder" class="btn btn-error rounded-full">
                        <span wire:loading.remove wire:target="cancelOrder">
                            Ya, Batalkan
                        </span>

                        <span wire:loading wire:target="cancelOrder" class="flex items-center gap-2">
                            <span class="loading loading-spinner loading-sm"></span>
                            Proses...
                        </span>
                    </button>
                </div>
            </div>

            <div class="modal-backdrop" wire:click="closeCancelModal"></div>
        </div>
    @endif
</div>
