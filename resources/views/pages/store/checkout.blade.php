<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Jobs\ExpireOrderJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Services\OrderCalculationService;
use App\Services\MidtransService;
use App\Services\ShippingService;
new #[Title('Checkout')] class extends Component {
    public ?int $selectedAddressId = null;
    public string $notes = '';
    public ?string $checkoutError = null;
    public ?string $selectedShippingKey = null;
    public array $shippingOptions = [];

    public string $voucherInput = '';
    public ?string $appliedVoucherCode = null;
    public ?string $voucherError = null;
    public ?string $voucherSuccess = null;

    public function mount(): void
    {
        $this->selectedAddressId = $this->defaultAddress?->id;
        $this->loadShippingOptions();
    }

    #[Computed]
    public function customer()
    {
        return Auth::guard('customer')->user();
    }

    #[Computed]
    public function cart()
    {
        return Cart::query()
            ->with(['items.product.primaryImage', 'items.variant'])
            ->where('customer_id', $this->customer->id)
            ->first();
    }

    #[Computed]
    public function items()
    {
        return $this->cart?->items ?? collect();
    }

    #[Computed]
    public function addresses()
    {
        return Address::query()->where('customer_id', $this->customer->id)->orderByDesc('is_default')->latest()->get();
    }

    #[Computed]
    public function defaultAddress()
    {
        return $this->addresses->firstWhere('is_default', true) ?: $this->addresses->first();
    }

    #[Computed]
    public function selectedAddress()
    {
        if (!$this->selectedAddressId) {
            return null;
        }

        return $this->addresses->firstWhere('id', (int) $this->selectedAddressId);
    }

    #[Computed]
    public function calculation(): array
    {
        return app(OrderCalculationService::class)->calculate(
            customer: $this->customer,
            rawCartItems: $this->items,
            address: $this->selectedAddress,
            shippingKey: $this->selectedShippingKey,
            voucherCode: $this->appliedVoucherCode
        );
    }

    #[Computed]
    public function subtotal(): float
    {
        return (float) ($this->calculation['subtotal'] ?? 0);
    }

    #[Computed]
    public function totalWeight(): int
    {
        return (int) ($this->calculation['total_weight'] ?? 0);
    }

    #[Computed]
    public function shippingCost(): float
    {
        return (float) ($this->calculation['shipping_cost'] ?? 0);
    }

    #[Computed]
    public function voucherValidation(): array
    {
        return $this->calculation['voucher_result'] ?? [
            'valid' => false,
            'message' => null,
            'voucher' => null,
            'product_discount' => 0.0,
            'shipping_discount' => 0.0,
            'total_discount' => 0.0,
        ];
    }

    #[Computed]
    public function appliedVoucher(): ?Voucher
    {
        return $this->calculation['voucher'] ?? null;
    }

    #[Computed]
    public function productDiscount(): float
    {
        return (float) ($this->calculation['product_discount'] ?? 0);
    }

    #[Computed]
    public function shippingDiscount(): float
    {
        return (float) ($this->calculation['shipping_discount'] ?? 0);
    }

    #[Computed]
    public function discount(): float
    {
        return (float) ($this->calculation['discount'] ?? 0);
    }

    #[Computed]
    public function grandTotal(): float
    {
        return (float) ($this->calculation['grand_total'] ?? 0);
    }

    public function applyVoucher(): void
    {
        $this->voucherError = null;
        $this->voucherSuccess = null;

        $code = strtoupper(trim($this->voucherInput));
        if ($code === '') {
            $this->voucherError = 'Silakan masukkan kode voucher.';
            return;
        }

        $result = app(VoucherService::class)->validateAndCalculate(
            $code,
            $this->customer,
            $this->subtotal,
            $this->shippingCost,
            $this->items
        );

        if (! $result['valid']) {
            $this->voucherError = $result['message'];
            $this->appliedVoucherCode = null;
            return;
        }

        $this->appliedVoucherCode = $result['voucher']->code;
        $this->voucherSuccess = $result['message'];
        $this->voucherInput = '';
    }

    public function removeVoucher(): void
    {
        $this->appliedVoucherCode = null;
        $this->voucherError = null;
        $this->voucherSuccess = null;
        $this->voucherInput = '';
    }

    public function shippingAddressText(Address $address): string
    {
        return collect([$address->full_address, $address->subdistrict, $address->district, $address->city, $address->province, $address->postal_code])
            ->filter()
            ->implode(', ');
    }

    public function placeOrder(): void
    {
        $this->checkoutError = null;

        if ($this->items->isEmpty()) {
            $this->checkoutError = 'Keranjang masih kosong.';
            return;
        }

        if (!$this->selectedAddress) {
            $this->checkoutError = 'Pilih alamat pengiriman terlebih dahulu.';
            return;
        }

        if (!$this->selectedShippingKey) {
            $this->checkoutError = 'Pilih jasa pengiriman terlebih dahulu.';
            return;
        }

        try {
            $order = app(OrderCalculationService::class)->placeOrder(
                customer: $this->customer,
                address: $this->selectedAddress,
                shippingKey: $this->selectedShippingKey,
                voucherCode: $this->appliedVoucherCode,
                notes: $this->notes ?: null
            );

            $this->dispatch('cart-updated', count: 0);

            // If grand_total == 0 (e.g. 100% free voucher), order is auto-paid
            if ($order->grand_total <= 0) {
                session()->flash('flash.type', 'success');
                session()->flash('flash.title', 'Pesanan Berhasil');
                session()->flash('flash.message', 'Pesanan ' . $order->order_number . ' lunas dengan voucher.');
                $this->redirectRoute('store.orders.show', $order, navigate: true);
                return;
            }

            // Create Midtrans Snap transaction
            $snap = app(MidtransService::class)->createSnapTransaction($order);

            $order->update([
                'midtrans_order_id' => $order->order_number,
                'midtrans_snap_token' => $snap->token,
                'midtrans_redirect_url' => $snap->redirect_url,
            ]);

            session()->flash('flash.type', 'success');
            session()->flash('flash.title', 'Order berhasil dibuat');
            session()->flash('flash.message', 'Pesanan ' . $order->order_number . ' berhasil dibuat. Silakan lanjutkan pembayaran.');

            $this->redirectRoute('store.orders.show', $order, navigate: true);
        } catch (\Throwable $e) {
            $this->checkoutError = $e->getMessage();
        }
    }

    public function updatedSelectedAddressId(): void
    {
        $this->selectedShippingKey = null;
        $this->shippingOptions = [];
        $this->loadShippingOptions();
    }
    public function loadShippingOptions(): void
    {
        $this->checkoutError = null;
        $this->selectedShippingKey = null;
        $this->shippingOptions = [];

        if (!$this->selectedAddress) {
            return;
        }

        if ($this->items->isEmpty()) {
            return;
        }

        if ($this->totalWeight <= 0) {
            $this->checkoutError = 'Total berat produk belum valid.';
            return;
        }

        if (!$this->selectedAddress->biteship_area_id) {
            $this->checkoutError = 'Alamat belum punya area Biteship. Silakan edit alamat dan pilih area pengiriman.';
            return;
        }

        try {
            $this->shippingOptions = app(OrderCalculationService::class)->getVerifiedShippingRates(
                customer: $this->customer,
                address: $this->selectedAddress,
                totalWeight: $this->totalWeight,
                cartItems: $this->items
            );
        } catch (\Throwable $e) {
            report($e);

            $this->checkoutError = 'Gagal mengambil ongkir. Coba lagi beberapa saat.';
            return;
        }

        if (empty($this->shippingOptions)) {
            $this->checkoutError = 'Opsi pengiriman tidak ditemukan.';
            return;
        }

        $this->selectedShippingKey = $this->shippingOptions[0]['key'];
    }
    public function selectedShipping(): ?array
    {
        if (!$this->selectedShippingKey || !$this->selectedAddress) {
            return null;
        }

        return app(OrderCalculationService::class)->findVerifiedShippingRate(
            customer: $this->customer,
            address: $this->selectedAddress,
            totalWeight: $this->totalWeight,
            cartItems: $this->items,
            shippingKey: $this->selectedShippingKey
        );
    }
};
?>

<div class="min-h-screen bg-base-200 pb-32">
    {{-- Fullscreen Loading --}}
    <div wire:loading.flex wire:target="placeOrder,loadShippingOptions"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>

            <p class="text-sm font-semibold text-base-content/70">
                Loading....
            </p>
        </div>
    </div>

    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.cart') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div>
                <h1 class="text-lg font-black">Checkout</h1>
                <p class="text-xs text-base-content/50">
                    Review pesanan sebelum bayar
                </p>
            </div>
        </div>
    </header>

    <main class="space-y-4 p-4">
        @if ($checkoutError)
            <div class="alert alert-error alert-soft rounded-2xl">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                <span class="text-sm">{{ $checkoutError }}</span>
            </div>
        @endif

        @if ($this->items->isEmpty())
            <section class="rounded-[2rem] bg-base-100 p-6 text-center shadow-sm">
                <div
                    class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                    <x-heroicon-o-shopping-bag class="h-12 w-12" />
                </div>

                <h2 class="mt-4 text-xl font-black">Keranjang kosong</h2>

                <p class="mt-2 text-sm text-base-content/60">
                    Tambahkan produk dulu sebelum checkout.
                </p>

                <a href="{{ route('store.products') }}" wire:navigate class="btn btn-primary mt-5 rounded-full">
                    Belanja Produk
                </a>
            </section>
        @else
            {{-- Address --}}
            <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-black">Alamat Pengiriman</h2>
                        <p class="text-xs text-base-content/50">
                            Pilih alamat tujuan
                        </p>
                    </div>

                    <a href="{{ route('store.addresses.create') }}" wire:navigate
                        class="btn btn-primary btn-xs rounded-full">
                        Tambah
                    </a>
                </div>

                @if ($this->addresses->isEmpty())
                    <div class="rounded-2xl bg-base-200 p-4 text-center">
                        <p class="text-sm font-bold">Belum ada alamat</p>
                        <p class="mt-1 text-xs text-base-content/50">
                            Tambahkan alamat dulu untuk checkout.
                        </p>

                        <a href="{{ route('store.addresses.create') }}" wire:navigate
                            class="btn btn-primary btn-sm mt-3 rounded-full">
                            Tambah Alamat
                        </a>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($this->addresses as $address)
                            <label
                                class="flex cursor-pointer gap-3 rounded-2xl border p-3 transition
                                    {{ (int) $selectedAddressId === $address->id ? 'border-primary bg-primary/10' : 'border-base-300 bg-base-100' }}">
                                <input type="radio" wire:model.live="selectedAddressId" value="{{ $address->id }}"
                                    class="radio radio-primary mt-1">

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-black">
                                            {{ $address->label ?: 'Alamat' }}
                                        </p>

                                        @if ($address->is_default)
                                            <span class="badge badge-primary badge-sm">Utama</span>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm font-bold">
                                        {{ $address->recipient_name }}
                                    </p>

                                    <p class="text-sm text-base-content/60">
                                        {{ $address->recipient_phone }}
                                    </p>

                                    <p class="mt-2 text-sm leading-relaxed text-base-content/70">
                                        {{ $this->shippingAddressText($address) }}
                                    </p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Items --}}
            <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                <div class="mb-3">
                    <h2 class="font-black">Produk Dipesan</h2>
                    <p class="text-xs text-base-content/50">
                        {{ $this->items->count() }} item produk
                    </p>
                </div>

                <div class="space-y-3">
                    @foreach ($this->items as $item)
                        @php
                            $image = $item->variant?->image ?: $item->product?->primaryImage?->image;
                            $weight = $item->variant?->weight_gram ?? ($item->product?->weight_gram ?? 0);
                        @endphp

                        <div class="flex gap-3">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-base-200">
                                @if ($image)
                                    <img src="{{ Storage::disk('public')->url($image) }}"
                                        class="h-full w-full object-cover" alt="{{ $item->product?->name }}">
                                @else
                                    <div class="flex h-full w-full items-center justify-center">
                                        <x-heroicon-o-photo class="h-7 w-7 text-base-content/30" />
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm font-bold">
                                    {{ $item->product?->name }}
                                </p>

                                @if ($item->variant)
                                    <p class="mt-1 line-clamp-1 text-xs text-base-content/50">
                                        {{ $item->variant->name }}
                                    </p>
                                @endif

                                <p class="mt-1 text-xs text-base-content/50">
                                    Berat {{ number_format($weight * $item->qty, 0, ',', '.') }} gram
                                </p>

                                <div class="mt-2 flex items-center justify-between">
                                    <p class="text-sm font-black text-primary">
                                        Rp {{ number_format($item->variant?->price ?? $item->product?->price ?? $item->price, 0, ',', '.') }}
                                    </p>

                                    <p class="text-xs text-base-content/50">
                                        x{{ $item->qty }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Notes --}}
            <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                <label class="mb-1 block text-sm font-bold">Catatan Pesanan</label>

                <textarea wire:model.live.debounce.500ms="notes" rows="3" class="textarea textarea-bordered w-full rounded-2xl"
                    placeholder="Contoh: kirim sore hari, packing rapi, dll."></textarea>
            </section>

            {{-- Voucher / Promo Code --}}
            <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-ticket class="h-5 w-5 text-primary" />
                        <h2 class="font-black text-sm md:text-base">Voucher Promo</h2>
                    </div>
                    <a href="{{ route('store.promos') }}" wire:navigate class="text-xs text-primary font-semibold hover:underline">
                        Lihat Voucher
                    </a>
                </div>

                @if($this->appliedVoucher)
                    <div class="flex items-center justify-between p-3.5 bg-primary/10 border border-primary/30 rounded-2xl">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-xl bg-primary text-primary-content flex items-center justify-center shrink-0">
                                <x-heroicon-o-check class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-mono font-bold text-xs md:text-sm text-primary truncate">
                                    {{ $this->appliedVoucher->code }}
                                </p>
                                <p class="text-[11px] text-base-content/70 truncate">
                                    {{ $this->appliedVoucher->name }} (Hemat Rp {{ number_format($this->discount, 0, ',', '.') }})
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="removeVoucher" class="btn btn-ghost btn-xs text-error font-semibold hover:bg-error/10 rounded-lg">
                            Hapus
                        </button>
                    </div>

                    @if($voucherSuccess)
                        <p class="text-xs text-success mt-2 font-medium flex items-center gap-1">
                            <x-heroicon-o-check-circle class="h-4 w-4 shrink-0" />
                            {{ $voucherSuccess }}
                        </p>
                    @endif
                @else
                    <div class="flex gap-2">
                        <input
                            type="text"
                            wire:model="voucherInput"
                            wire:keydown.enter.prevent="applyVoucher"
                            placeholder="Masukkan kode voucher"
                            class="input input-bordered input-sm flex-1 rounded-xl font-mono uppercase text-xs focus:border-primary"
                        />
                        <button
                            type="button"
                            wire:click="applyVoucher"
                            wire:loading.attr="disabled"
                            wire:target="applyVoucher"
                            class="btn btn-primary btn-sm rounded-xl px-4 text-xs font-bold"
                        >
                            <span wire:loading.remove wire:target="applyVoucher">Gunakan</span>
                            <span wire:loading wire:target="applyVoucher" class="loading loading-spinner loading-xs"></span>
                        </button>
                    </div>

                    @if($voucherError)
                        <p class="text-xs text-error mt-2 font-medium flex items-center gap-1">
                            <x-heroicon-o-exclamation-circle class="h-4 w-4 shrink-0" />
                            {{ $voucherError }}
                        </p>
                    @endif
                @endif
            </section>

            {{-- Summary --}}
            <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                <h2 class="mb-3 font-black">Ringkasan Pembayaran</h2>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Subtotal Produk</span>
                        <span class="font-bold">
                            Rp {{ number_format($this->subtotal, 0, ',', '.') }}
                        </span>
                    </div>

                    @if($this->productDiscount > 0)
                        <div class="flex justify-between text-success">
                            <span>Diskon Produk</span>
                            <span class="font-bold">
                                - Rp {{ number_format($this->productDiscount, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-base-content/60">Biaya Pengiriman</span>
                        <span class="font-bold">
                            Rp {{ number_format($this->shippingCost, 0, ',', '.') }}
                        </span>
                    </div>

                    @if($this->shippingDiscount > 0)
                        <div class="flex justify-between text-success">
                            <span>Diskon Pengiriman</span>
                            <span class="font-bold">
                                - Rp {{ number_format($this->shippingDiscount, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-base-content/60">Total Berat</span>
                        <span class="font-bold">
                            {{ number_format($this->totalWeight, 0, ',', '.') }} gram
                        </span>
                    </div>

                    <div class="border-t border-base-200 pt-2">
                        <div class="flex justify-between">
                            <span class="font-black">Total Pembayaran</span>
                            <span class="text-lg font-black text-primary">
                                Rp {{ number_format($this->grandTotal, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Shipping --}}
        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h2 class="font-black">Pengiriman</h2>
                    <p class="text-xs text-base-content/50">
                        Pilih jasa pengiriman
                    </p>
                </div>

                <button type="button" wire:click="loadShippingOptions" wire:loading.attr="disabled"
                    wire:target="loadShippingOptions" class="btn btn-ghost btn-xs rounded-full"
                    @disabled(!$selectedAddressId)>
                    <span wire:loading.remove wire:target="loadShippingOptions">Refresh</span>
                    <span wire:loading wire:target="loadShippingOptions"
                        class="loading loading-spinner loading-xs"></span>
                </button>
            </div>


            <div wire:loading.remove wire:target="loadShippingOptions">
                @if (empty($shippingOptions))
                    <div class="rounded-2xl bg-base-200 p-4 text-center">
                        <p class="text-sm font-bold">Belum ada opsi pengiriman</p>
                        <p class="mt-1 text-xs text-base-content/50">
                            Pilih alamat dulu atau klik refresh.
                        </p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($shippingOptions as $shipping)
                            <label
                                class="flex cursor-pointer gap-3 rounded-2xl border p-3 transition
                        {{ $selectedShippingKey === $shipping['key'] ? 'border-primary bg-primary/10' : 'border-base-300 bg-base-100' }}">
                                <input type="radio" wire:model.live="selectedShippingKey"
                                    value="{{ $shipping['key'] }}" class="radio radio-primary mt-1">

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="font-black">
                                            {{ strtoupper($shipping['code']) }} {{ $shipping['service'] }}
                                        </p>

                                        <p class="shrink-0 font-black text-primary">
                                            Rp {{ number_format($shipping['cost'], 0, ',', '.') }}
                                        </p>
                                    </div>

                                    <p class="mt-1 text-xs text-base-content/50">
                                        {{ $shipping['description'] ?: $shipping['name'] }}
                                    </p>

                                    @if ($shipping['etd'])
                                        <p class="mt-1 text-xs text-base-content/60">
                                            Estimasi {{ $shipping['etd'] }}
                                        </p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </main>

    @if ($this->items->isNotEmpty())
        <div
            class="fixed bottom-16 left-1/2 z-50 w-full max-w-md -translate-x-1/2 border-t border-base-300 bg-base-100 p-4">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-base-content/50">Total Bayar</p>
                    <p class="truncate text-lg font-black text-primary">
                        Rp {{ number_format($this->grandTotal, 0, ',', '.') }}
                    </p>
                </div>

                <button type="button" wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder"
                    class="btn btn-primary rounded-full px-6" @disabled($this->addresses->isEmpty())>
                    Buat Order
                </button>
            </div>
        </div>
    @endif
</div>
