<?php

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Keranjang')] class extends Component {
    public ?string $message = null;

    #[Computed]
    public function cart()
    {
        $customer = Auth::guard('customer')->user();

        return Cart::query()
            ->with([
                'items.product.primaryImage',
                'items.variant',
            ])
            ->when(
                $customer,
                fn ($query) => $query->where('customer_id', $customer->id),
                fn ($query) => $query->where('session_id', session()->getId())
            )
            ->first();
    }

    #[Computed]
    public function items()
    {
        return $this->cart?->items ?? collect();
    }

    #[Computed]
    public function subtotal(): float
    {
        return (float) $this->items->sum(function ($item) {
            $price = $item->variant?->price ?? $item->product?->price ?? $item->price;
            return (float) ($price * $item->qty);
        });
    }

    #[Computed]
    public function hasInactiveItems(): bool
    {
        return $this->items->contains(function ($item) {
            if (! $item->product || ! $item->product->is_active) {
                return true;
            }
            if ($item->variant && ! $item->variant->is_active) {
                return true;
            }
            return false;
        });
    }

    public function cartCount(): int
    {
        return (int) $this->items->sum('qty');
    }

    public function incrementItem(int $itemId): void
    {
        $item = $this->items->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $stock = (int) ($item->variant?->available_stock ?? $item->product?->available_stock ?? 0);

        if ($item->qty >= $stock) {
            $this->message = 'Jumlah sudah mencapai stok tersedia.';
            return;
        }

        $item->increment('qty');

        unset($this->cart, $this->items, $this->subtotal);

        $this->dispatch('cart-updated', count: $this->cartCount());
    }

    public function decrementItem(int $itemId): void
    {
        $item = $this->items->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        if ($item->qty <= 1) {
            $item->delete();
        } else {
            $item->decrement('qty');
        }

        unset($this->cart, $this->items, $this->subtotal);

        $this->dispatch('cart-updated', count: $this->cartCount());
    }

    public function removeItem(int $itemId): void
    {
        $item = $this->items->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $item->delete();

        unset($this->cart, $this->items, $this->subtotal);

        $this->message = 'Produk dihapus dari keranjang.';

        $this->dispatch('cart-updated', count: $this->cartCount());
    }
    public function isGuest(): bool
    {
        return ! Auth::guard('customer')->check();
    }
};
?>

<div class="min-h-screen bg-base-200 pb-28">
    @if ($this->isGuest())
    <div class="flex min-h-[calc(100vh-5rem)] items-center justify-center p-4">
        <section class="w-full rounded-[2rem] bg-base-100 p-6 text-center shadow-sm">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                <x-heroicon-o-user-circle class="h-12 w-12" />
            </div>

            <h2 class="mt-4 text-xl font-black">
                Login untuk melihat keranjang
            </h2>

            <p class="mt-2 text-sm leading-relaxed text-base-content/60">
                Keranjang belanja hanya tersedia untuk customer yang sudah login.
            </p>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <a href="{{ route('customer.login') }}" wire:navigate class="btn btn-primary rounded-full">
                    Login
                </a>

                <a href="{{ route('customer.register') }}" wire:navigate class="btn btn-outline rounded-full">
                    Register
                </a>
            </div>

            {{-- <a href="{{ route('store.products') }}" wire:navigate class="btn btn-ghost mt-3 rounded-full">
                Lanjut 
            </a> --}}
        </section>
    </div>
    @else
    @if ($message)
    <div x-data="{ show: true }" x-init="setTimeout(() => {
                    show = false
                    $wire.set('message', null)
                }, 2500)" x-show="show" x-transition class="toast toast-top toast-center z-[9999]">
        <div class="alert alert-info rounded-2xl shadow-lg">
            <x-heroicon-o-information-circle class="h-5 w-5" />
            <span class="text-sm font-semibold">{{ $message }}</span>
        </div>
    </div>
    @endif

    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div>
                <h1 class="text-lg font-black">Keranjang</h1>
                <p class="text-xs text-base-content/50">
                    {{ $this->cartCount() }} item di keranjang
                </p>
            </div>
        </div>
    </header>

    <main class="p-4">
        @if ($this->items->isEmpty())
        <section class="rounded-[2rem] bg-base-100 p-6 text-center shadow-sm">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                <x-heroicon-o-shopping-bag class="h-12 w-12" />
            </div>

            <h2 class="mt-4 text-xl font-black">Keranjang kosong</h2>

            <p class="mt-2 text-sm text-base-content/60">
                Yuk pilih produk favoritmu dulu.
            </p>

            <a href="{{ route('store.products') }}" wire:navigate class="btn btn-primary mt-5 rounded-full">
                Mulai Belanja
            </a>
        </section>
        @else
        <section class="space-y-3">
            @foreach ($this->items as $item)
            @php
            $image = $item->variant?->image ?: $item->product?->primaryImage?->image;
            $stock = (int) ($item->variant?->available_stock ?? $item->product?->available_stock ?? 0);
            $unitPrice = (int) round((float) ($item->variant?->price ?? $item->product?->price ?? $item->price));
            $isItemActive = ($item->product?->is_active ?? false) && ($item->variant ? ($item->variant->is_active ?? false) : true);
            @endphp

            <div class="rounded-[2rem] bg-base-100 p-3 shadow-sm {{ ! $isItemActive ? 'opacity-60 border border-error/40' : '' }}">
                <div class="flex gap-3">
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-base-200">
                        @if ($image)
                        <img src="{{ Storage::disk('public')->url($image) }}" alt="{{ $item->product?->name }}"
                            class="h-full w-full object-cover">
                        @else
                        <div class="flex h-full w-full items-center justify-center">
                            <x-heroicon-o-photo class="h-8 w-8 text-base-content/30" />
                        </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="line-clamp-2 text-sm font-bold">
                                    {{ $item->product?->name }}
                                </h3>

                                @if ($item->variant)
                                <p class="mt-1 line-clamp-1 text-xs text-base-content/50">
                                    {{ $item->variant->name }}
                                </p>
                                @endif

                                @if (! $isItemActive)
                                <span class="badge badge-error badge-xs mt-1">Produk Tidak Aktif</span>
                                @endif
                            </div>

                            <button type="button" wire:click="removeItem({{ $item->id }})"
                                class="btn btn-ghost btn-xs btn-circle text-error">
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        </div>

                        <p class="mt-2 font-black text-primary">
                            Rp {{ number_format($unitPrice, 0, ',', '.') }}
                        </p>

                        <div class="mt-3 flex items-center justify-between">
                            <p class="text-xs text-base-content/50">
                                Stok {{ $stock }}
                            </p>

                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="decrementItem({{ $item->id }})"
                                    class="btn btn-circle btn-outline btn-xs">
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                </button>

                                <span class="w-6 text-center text-sm font-bold">
                                    {{ $item->qty }}
                                </span>

                                <button type="button" wire:click="incrementItem({{ $item->id }})"
                                    class="btn btn-circle btn-outline btn-xs" @disabled($item->qty >= $stock || ! $isItemActive)
                                    >
                                    <x-heroicon-o-plus class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 border-t border-base-200 pt-3 text-right">
                    <p class="text-xs text-base-content/50">Subtotal</p>
                    <p class="font-black">
                        Rp {{ number_format($unitPrice * $item->qty, 0, ',', '.') }}
                    </p>
                </div>
            </div>
            @endforeach
        </section>
        @endif
    </main>

    @if ($this->items->isNotEmpty())
    <div
        class="fixed bottom-16 left-1/2 z-50 w-full max-w-md -translate-x-1/2 border-t border-base-300 bg-base-100 p-4">
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-base-content/50">Subtotal</p>
                <p class="truncate text-lg font-black text-primary">
                    Rp {{ number_format($this->subtotal, 0, ',', '.') }}
                </p>
            </div>

            @if ($this->hasInactiveItems)
            <button type="button" disabled class="btn btn-error btn-outline rounded-full px-4 text-xs" title="Ada produk yang tidak aktif">
                Ada Produk Nonaktif
            </button>
            @else
            <a href="{{ route('store.checkout') }}" wire:navigate class="btn btn-primary rounded-full px-6">
                Checkout
            </a>
            @endif
        </div>
    </div>
    @endif
    @endif
</div>