<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;
use App\Models\CartItem;
new #[Title('Product Detail')] class extends Component {
    public string $slug;

    public ?int $selectedVariantId = null;
    public ?string $cartSuccessMessage = null;
    public ?string $cartSuccess = null;
    public bool $showLoginRequired = false;
    public int $qty = 1;

    public function cartCount(): int
    {
        $cart = $this->getOrCreateCart();

        return (int) $cart->items()->sum('qty');
    }

    // public function getOrCreateCart(): Cart
    // {
    //     $customer = Auth::guard('customer')->user();

    //     if ($customer) {
    //         return Cart::firstOrCreate([
    //             'customer_id' => $customer->id,
    //         ]);
    //     }

    //     return Cart::firstOrCreate([
    //         'session_id' => session()->getId(),
    //     ]);
    // }
    public function getOrCreateCart(): Cart
    {
        $customer = Auth::guard('customer')->user();

        return Cart::firstOrCreate([
            'customer_id' => $customer->id,
        ]);
    }

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        $firstVariant = $this->product->variants->where('is_active', true)->first();

        $this->selectedVariantId = $firstVariant?->id;
    }

    #[Computed]
    public function product()
    {
        return Product::query()
            ->with(['category', 'images', 'primaryImage', 'variants' => fn($query) => $query->where('is_active', true)->orderBy('price')])
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    #[Computed]
    public function selectedVariant()
    {
        if (!$this->selectedVariantId) {
            return null;
        }

        return ProductVariant::query()->where('product_id', $this->product->id)->where('is_active', true)->find($this->selectedVariantId);
    }

    #[Computed]
    public function displayImage(): ?string
    {
        return $this->selectedVariant?->image ?: $this->product->primaryImage?->image ?: $this->product->images->first()?->image;
    }

    #[Computed]
    public function displayPrice(): float
    {
        return (float) ($this->selectedVariant?->price ?? $this->product->price);
    }

    #[Computed]
    public function displayStock(): int
    {
        return (int) ($this->selectedVariant?->stock ?? $this->product->stock);
    }

    #[Computed]
    public function displayWeight(): int
    {
        return (int) ($this->selectedVariant?->weight_gram ?? $this->product->weight_gram);
    }

    public function incrementQty(): void
    {
        if ($this->qty < $this->displayStock) {
            $this->qty++;
        }
    }

    public function decrementQty(): void
    {
        if ($this->qty > 1) {
            $this->qty--;
        }
    }

    public function updatedSelectedVariantId(): void
    {
        $this->qty = 1;
        $this->resetErrorBag();
    }

    public function addToCart(): void
    {
        $this->cartSuccessMessage = null;
        $this->showLoginRequired = false;

        if (!Auth::guard('customer')->check()) {
            $this->showLoginRequired = true;
            return;
        }

        if ($this->product->variants->count() > 0 && !$this->selectedVariant) {
            $this->addError('selectedVariantId', 'Pilih varian terlebih dahulu.');
            return;
        }

        if ($this->displayStock <= 0) {
            $this->addError('qty', 'Stok varian ini habis.');
            return;
        }

        if ($this->qty > $this->displayStock) {
            $this->addError('qty', 'Jumlah melebihi stok tersedia.');
            return;
        }

        $cart = $this->getOrCreateCart();

        $variantId = $this->selectedVariant?->id;

        $existingItem = CartItem::query()->where('cart_id', $cart->id)->where('product_id', $this->product->id)->where('product_variant_id', $variantId)->first();

        if ($existingItem) {
            $newQty = $existingItem->qty + $this->qty;

            if ($newQty > $this->displayStock) {
                $this->addError('qty', 'Jumlah di keranjang melebihi stok tersedia.');
                return;
            }

            $existingItem->update([
                'qty' => $newQty,
                'price' => $this->displayPrice,
            ]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $this->product->id,
                'product_variant_id' => $variantId,
                'qty' => $this->qty,
                'price' => $this->displayPrice,
            ]);
        }

        $this->cartSuccessMessage = 'Produk berhasil ditambahkan ke keranjang.';

        $this->dispatch('cart-updated', count: $this->cartCount());
    }
};
?>

<div class="min-h-screen bg-base-200 pb-28">
    {{-- Header image --}}
    <section class="relative bg-base-100">
        <div class="absolute left-4 top-4 z-20">
            <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm bg-base-100/90 shadow">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>
        </div>

        {{-- <div class="absolute right-4 top-4 z-20">
            <button class="btn btn-circle btn-sm bg-base-100/90 shadow">
                <x-heroicon-o-heart class="h-5 w-5" />
            </button>
        </div> --}}

        <div class="aspect-square w-full bg-base-300">
            @if ($this->displayImage)
                <img src="{{ Storage::disk('public')->url($this->displayImage) }}" alt="{{ $this->product->name }}"
                    class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <x-heroicon-o-photo class="h-16 w-16 text-base-content/30" />
                </div>
            @endif
        </div>
    </section>

    {{-- Content --}}
    <main class="-mt-6 relative z-10 rounded-t-[2rem] bg-base-100 p-4">
        @if ($cartSuccessMessage)
            <div x-data="{ show: true }" x-init="setTimeout(() => {
                show = false
                $wire.set('cartSuccessMessage', null)
            }, 2500)" x-show="show" x-transition
                class="toast toast-top toast-center z-[9999]">
                <div class="alert alert-success rounded-2xl shadow-lg">
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    <span class="text-sm font-semibold">
                        {{ $cartSuccessMessage }}
                    </span>
                </div>
            </div>
        @endif

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-medium text-base-content/50">
                    {{ $this->product->category?->name ?? '-' }}
                </p>

                <h1 class="mt-1 text-2xl font-black leading-tight">
                    {{ $this->product->name }}
                </h1>
            </div>


        </div>

        <div class="mt-4 flex items-end justify-between">
            <div>
                <p class="text-xs text-base-content/50">Harga</p>
                <p class="text-2xl font-black text-primary">
                    Rp {{ number_format($this->displayPrice, 0, ',', '.') }}
                </p>
            </div>

            <div class="text-right">
                <p class="text-xs text-base-content/50">Stok</p>
                <p class="font-bold">
                    {{ $this->displayStock }}
                </p>
            </div>
        </div>

        <div class="mt-2 flex items-center gap-2 text-sm text-base-content/60">
            <x-heroicon-o-scale class="h-4 w-4" />
            <span>Berat {{ number_format($this->displayWeight, 0, ',', '.') }} gram</span>
        </div>

        {{-- Variants --}}
        @if ($this->product->variants->count() > 0)
            <section class="mt-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Pilih Varian</h2>
                    <span class="text-xs text-base-content/50">
                        {{ $this->product->variants->count() }} pilihan
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-2">
                    @foreach ($this->product->variants as $variant)
                        <label
                            class="flex cursor-pointer items-center justify-between rounded-2xl border p-3 transition
                                {{ (int) $selectedVariantId === $variant->id ? 'border-primary bg-primary/10' : 'border-base-300 bg-base-100' }}">
                            <div class="min-w-0">
                                <p class="font-semibold leading-tight">
                                    {{ $variant->name }}
                                </p>

                                <p class="mt-1 text-xs text-base-content/50">
                                    SKU: {{ $variant->sku }} · Stok {{ $variant->stock }}
                                </p>

                                <p class="mt-1 text-sm font-bold text-primary">
                                    Rp {{ number_format($variant->price, 0, ',', '.') }}
                                </p>
                            </div>

                            <input type="radio" wire:model.live="selectedVariantId" value="{{ $variant->id }}"
                                class="radio radio-primary">
                        </label>
                    @endforeach
                </div>

                @error('selectedVariantId')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </section>
        @endif

        {{-- Qty --}}
        <section class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-bold">Jumlah</h2>

                @if ($this->displayStock <= 0)
                    <span class="badge badge-error">Stok habis</span>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button type="button" wire:click="decrementQty" class="btn btn-circle btn-outline btn-sm"
                    @disabled($qty <= 1)>
                    <x-heroicon-o-minus class="h-4 w-4" />
                </button>

                <input type="number" min="1" max="{{ $this->displayStock }}" wire:model.live="qty"
                    class="input input-bordered h-10 w-20 text-center font-bold">

                <button type="button" wire:click="incrementQty" class="btn btn-circle btn-outline btn-sm"
                    @disabled($qty >= $this->displayStock)>
                    <x-heroicon-o-plus class="h-4 w-4" />
                </button>
            </div>

            @error('qty')
                <p class="mt-2 text-sm text-error">{{ $message }}</p>
            @enderror
        </section>

        {{-- Description --}}
        <section class="mt-6">
            <h2 class="mb-3 font-bold">Deskripsi Produk</h2>

            <div class="prose prose-sm max-w-none text-base-content/80">
                {!! $this->product->description ?: '<p>Belum ada deskripsi produk.</p>' !!}
            </div>
        </section>
    </main>

    {{-- Sticky bottom action --}}
    <div
        class="fixed bottom-16 left-1/2 z-50 w-full max-w-md -translate-x-1/2 border-t border-base-300 bg-base-100 p-4">
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-base-content/50">Total</p>
                <p class="truncate text-lg font-black text-primary">
                    Rp {{ number_format($this->displayPrice * $qty, 0, ',', '.') }}
                </p>
            </div>

            <button wire:click="addToCart" class="btn btn-primary rounded-full px-6" @disabled($this->displayStock <= 0)>
                <x-heroicon-o-shopping-bag class="h-5 w-5" />
                Keranjang
            </button>
        </div>
    </div>
    @if ($showLoginRequired)
        <div class="modal modal-open">
            <div class="modal-box rounded-[2rem]">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <x-heroicon-o-user-circle class="h-7 w-7" />
                    </div>

                    <div>
                        <h3 class="text-lg font-black">Login dulu ya</h3>

                        <p class="mt-1 text-sm leading-relaxed text-base-content/60">
                            Untuk menambahkan produk ke keranjang, kamu perlu login atau daftar akun terlebih dahulu.
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" wire:click="$set('showLoginRequired', false)"
                        class="btn btn-ghost rounded-full">
                        Nanti
                    </button>

                    <a href="{{ route('customer.login') }}" wire:navigate class="btn btn-primary rounded-full">
                        Login
                    </a>
                </div>

                <div class="mt-3 text-center">
                    <a href="{{ route('customer.register') }}" wire:navigate
                        class="text-sm font-semibold text-primary">
                        Belum punya akun? Daftar
                    </a>
                </div>
            </div>

            <div class="modal-backdrop" wire:click="$set('showLoginRequired', false)"></div>
        </div>
    @endif
</div>
